<?php
// Database connection
$dbFile = 'database.db';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}

// $pdo->exec("
// CREATE TABLE IF NOT EXISTS Status (
//     status_id INTEGER PRIMARY KEY AUTOINCREMENT,
//     status_name TEXT NOT NULL
// );

// CREATE TABLE IF NOT EXISTS Content (
//     content_id INTEGER PRIMARY KEY AUTOINCREMENT,
//     title TEXT NOT NULL,
//     description TEXT,
//     status_id INTEGER,
//     deadline TEXT,
//     created_at TEXT DEFAULT CURRENT_TIMESTAMP,
//     updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
//     FOREIGN KEY (status_id) REFERENCES Status(status_id)
// );

// INSERT INTO Status (status_name) VALUES ('Draft'), ('Published'), ('Archived');
// ");


// Fetch content with status
$stmt = $pdo->query('SELECT c.content_id, c.title, c.description, s.status_name, c.deadline, c.created_at, c.updated_at FROM Content c JOIN Status s ON c.status_id = s.status_id ORDER BY c.created_at DESC');
$contents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Load notification settings
$settingsFile = 'settings.json';
$notification_days = 1;
if (file_exists($settingsFile)) {
    $data = json_decode(file_get_contents($settingsFile), true);
    if (isset($data['notification_days'])) {
        $notification_days = (int)$data['notification_days'];
    }
}

// Find deadlines within notification window (exclude Published)
$today = new DateTime();
$notify_contents = [];
foreach ($contents as $row) {
    if (!empty($row['deadline']) && strtolower($row['status_name']) !== 'published') {
        $deadline = DateTime::createFromFormat('Y-m-d', $row['deadline']);
        if ($deadline) {
            $diff = (int)$today->diff($deadline)->format('%r%a');
            if ($diff >= 0 && $diff <= $notification_days) {
                $notify_contents[] = $row;
            }
        }
    }
}

// --- Search and Pagination ---
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 5;
$filtered = $contents;
if ($search !== '') {
    $filtered = array_filter($contents, function($row) use ($search) {
        return stripos($row['title'], $search) !== false || stripos($row['description'], $search) !== false;
    });
}
$total = count($filtered);
$totalPages = max(1, ceil($total / $perPage));
$filtered = array_slice(array_values($filtered), ($page-1)*$perPage, $perPage);
$from = $total ? ($page-1)*$perPage+1 : 0;
$to = min($from+$perPage-1, $total);

// --- Notification read state (JS will use localStorage) ---
$hasNoti = count($notify_contents) > 0 ? 'true' : 'false';
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Content Hub</title>
    <link rel="icon" type="image/x-icon" href="data:image/x-icon;base64," />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
      body {
      font-family: 'Segoe UI', 'Inter', 'Noto Sans', Arial, sans-serif;
      background: #f4f6fa;
        min-height: 100vh;
      margin: 0;
      padding: 0;
    }
    .app-header {
      width: 100vw;
      background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%);
      box-shadow: 0 2px 12px 0 rgba(31,38,135,0.08);
      padding: 0.7rem 0 0.7rem 0;
      margin-bottom: 0;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid #1976d2;
      z-index: 10;
      position: sticky;
      top: 0;
    }
    .app-header-left {
      display: flex;
      align-items: center;
      gap: 1.1rem;
      margin-left: 2vw;
    }
    .app-header .app-icon {
      width: 36px;
      height: 36px;
    }
    .app-header .app-title {
      font-size: 1.7rem;
      font-weight: 800;
      color: #fff;
      letter-spacing: 0.5px;
      text-shadow: 0 2px 8px rgba(31,38,135,0.10);
    }
    .app-header .header-action {
      margin-right: 2vw;
    }
    .btn {
      border-radius: 10px;
      font-weight: 700;
      font-size: 1.1em;
      padding: 0.6em 1.4em;
      box-shadow: 0 2px 8px 0 rgba(31,38,135,0.04);
      transition: background 0.15s, color 0.15s, box-shadow 0.15s, transform 0.1s;
    }
    .btn-primary {
      background: #1976d2;
      border: none;
      color: #fff;
    }
    .btn-primary:hover, .btn-primary:focus {
      background: #1565c0;
      color: #fff;
      box-shadow: 0 4px 16px 0 rgba(31,38,135,0.10);
      transform: translateY(-2px) scale(1.04);
    }
    .btn-outline-primary {
      color: #1976d2;
      border: 2px solid #1976d2;
      background: #f4f8ff;
    }
    .btn-outline-primary:hover {
      background: #1976d2;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
    }
    .btn-outline-danger {
      color: #d32f2f;
      border: 2px solid #d32f2f;
      background: #fff6f6;
    }
    .btn-outline-danger:hover {
      background: #d32f2f;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
    }
    .btn-outline-info {
      color: #0288d1;
      border: 2px solid #0288d1;
      background: #f0faff;
    }
    .btn-outline-info:hover {
      background: #0288d1;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
    }
    .main-content {
      max-width: 95%;
      margin: 2.5rem auto 0 auto;
      padding: 0 2vw;
      display: flex;
      flex-direction: column;
      gap: 2.2rem;
    }
    .table-area {
      background: #f6f8fb;
        border-radius: 12px;
      box-shadow: 0 2px 16px 0 rgba(31,38,135,0.10);
      border: 2px solid #d0d7e3;
      padding: 0.5rem 1.2rem 1.2rem 1.2rem;
    }
    .table-title-row th {
      font-size: 1.25rem;
      font-weight: 900;
      color: #1976d2;
      background: #e3eaf6 !important;
      border-bottom: 2.5px solid #b6c2d6 !important;
      letter-spacing: 0.5px;
    }
    .table thead th {
      font-size: 1.08rem;
      font-weight: 700;
      background: #e3eaf6;
      color: #1a237e;
      border-bottom: 2px solid #b6c2d6;
    }
    .table {
      border-radius: 8px;
      overflow: hidden;
      background: #f6f8fb;
      margin-bottom: 0;
    }
    .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: #f0f4fa;
    }
    .table-hover tbody tr:hover, .table-hover tbody tr:focus {
      background-color: #dbeafe;
      cursor: pointer;
      transition: background 0.2s;
      outline: 2px solid #1976d2;
    }
    .badge-status {
      font-size: 1em;
      padding: 0.5em 1em;
      border-radius: 1em;
      background: #e3e7ef;
      color: #1565c0;
      font-weight: 600;
    }
    .action-btns .btn {
      font-size: 1.08em;
      padding: 0.45em 1.1em;
      border-radius: 8px;
      font-weight: 700;
      margin-right: 0.2em;
      box-shadow: 0 2px 8px 0 rgba(31,38,135,0.06);
      transition: background 0.15s, color 0.15s, box-shadow 0.15s, transform 0.1s;
    }
    .btn-view {
      background: #1976d2;
      color: #fff;
      border: none;
    }
    .btn-view:hover, .btn-view:focus {
      background: #1565c0;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 4px 16px 0 rgba(31,38,135,0.13);
    }
    .btn-edit {
      background: #43a047;
      color: #fff;
      border: none;
    }
    .btn-edit:hover, .btn-edit:focus {
      background: #2e7d32;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 4px 16px 0 rgba(67,160,71,0.13);
    }
    .btn-delete {
      background: #e53935;
      color: #fff;
      border: none;
    }
    .btn-delete:hover, .btn-delete:focus {
      background: #b71c1c;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 4px 16px 0 rgba(229,57,53,0.13);
    }
    @media (max-width: 991px) {
      .main-content {
        max-width: 99vw;
        padding: 0 0.5rem;
      }
      .app-header .app-title {
        font-size: 1.1rem;
      }
      }
    </style>
  </head>
  <body>
  <div class="app-header">
    <div class="app-header-left">
       
        <span class="app-title">Content Hub</span>
      </div>
      <div class="header-action d-flex align-items-center gap-2">
        <a class="btn btn-primary d-inline-flex align-items-center" href="new.php">
          <i class="bi bi-plus-circle me-1"></i> New Content
        </a>
        <a href="settings.php" class="btn btn-outline-primary p-2 rounded-circle d-flex align-items-center justify-content-center ms-1 header-icon-btn" title="Settings">
          <i class="bi bi-gear-fill" style="font-size:1.35em;"></i>
        </a>
        <button id="notiBtn" class="btn btn-outline-primary p-2 rounded-circle d-flex align-items-center justify-content-center ms-1 position-relative header-icon-btn" title="Notifications" type="button" data-bs-toggle="modal" data-bs-target="#notiModal">
          <i class="bi bi-bell-fill" style="font-size:1.35em;"></i>
          <span id="notiDot" class="pulse-dot" style="display:none;"></span>
        </button>
      </div>
    </div>
  </div>
  <?php if (count($notify_contents) > 0): ?>
    <!-- Noti Modal -->
    <div class="modal fade" id="notiModal" tabindex="-1" aria-labelledby="notiModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <div class="modal-header bg-warning-subtle">
            <h5 class="modal-title" id="notiModalLabel"><i class="bi bi-bell-fill me-2 text-warning"></i>Deadline Notifications</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <?php if (count($notify_contents) > 0): ?>
              <ul class="list-group mb-3">
                <?php foreach ($notify_contents as $n): ?>
                  <li class="list-group-item d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
                    <div>
                      <b><?= htmlspecialchars($n['title']) ?></b>
                      <span class="badge badge-status ms-2">Status: <?= htmlspecialchars($n['status_name']) ?></span>
              </div>
                    <div class="text-danger fw-bold">
                      <?= htmlspecialchars($n['deadline']) ?>
            </div>
                </li>
                <?php endforeach; ?>
              </ul>
              <button id="markReadBtn" class="btn btn-outline-primary w-100" type="button">Mark all as read</button>
            <?php else: ?>
              <div class="text-center text-secondary">No notifications.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
            </div>
  <?php endif; ?>
  <div class="main-content">
    <div class="table-area">
      <form class="mb-3 d-flex gap-2" method="get" action="">
        <input type="text" class="form-control" name="search" placeholder="Search title or description..." value="<?= htmlspecialchars($search) ?>" style="max-width:320px;">
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i> Search</button>
        <?php if ($search !== ''): ?>
          <a href="index.php" class="btn btn-outline-secondary">Clear</a>
        <?php endif; ?>
      </form>
      <table class="table table-striped table-hover align-middle mb-0">
                <thead class="table-light">
                  <tr>
            <th scope="col" style="min-width: 200px; max-width: 320px;">Content Title</th>
            <th scope="col" style="min-width: 200px; max-width: 320px;">Description</th>
            <th scope="col" style="min-width: 180px;">Actions</th>
                    <th scope="col" style="min-width: 120px;">Status</th>
                    <th scope="col" style="min-width: 160px;">Date</th>
                  </tr>
                </thead>
                <tbody>
          <?php if (count($filtered) === 0): ?>
                  <tr><td colspan="5" class="text-center text-secondary">No content found.</td></tr>
                <?php else: ?>
            <?php foreach ($filtered as $row): ?>
            <tr>
              <td class="fw-semibold text-dark text-truncate" style="max-width: 320px;">
                <span title="<?= htmlspecialchars($row['title']) ?>">
                  <?= htmlspecialchars($row['title']) ?>
                </span>
              </td>
              <td class="text-truncate" style="max-width: 320px;">
                <span title="<?= htmlspecialchars($row['description']) ?>">
                  <?php
                    $desc = htmlspecialchars($row['description']);
                    if (mb_strlen($desc) > 60) {
                        echo mb_substr($desc, 0, 60) . '... ';
                    } else {
                        echo $desc;
                    }
                  ?>
                </span>
              </td>
              <td>
                <div class="action-btns d-flex flex-wrap flex-md-nowrap gap-2 justify-content-md-end align-items-center">
                  <button type="button" class="btn btn-view d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#viewDetailModal"
                    data-title="<?= htmlspecialchars($row['title']) ?>"
                    data-description="<?= htmlspecialchars($row['description']) ?>"
                    data-status="<?= htmlspecialchars($row['status_name']) ?>"
                    data-deadline="<?= htmlspecialchars($row['deadline']) ?>"
                    data-created="<?= htmlspecialchars($row['created_at']) ?>"
                    data-updated="<?= htmlspecialchars($row['updated_at']) ?>">
                    <i class="bi bi-eye me-1"></i>View
                  </button>
                  <a href="edit.php?id=<?= $row['content_id'] ?>" class="btn btn-edit d-inline-flex align-items-center">
                    <i class="bi bi-pencil me-1"></i>Edit
                  </a>
                  <a href="delete.php?id=<?= $row['content_id'] ?>" class="btn btn-delete d-inline-flex align-items-center" onclick="return confirm('Are you sure you want to delete this content?');">
                    <i class="bi bi-trash me-1"></i>Delete
                  </a>
                      </div>
                    </td>
                    <td>
                <span class="badge badge-status w-100">
                  <?= htmlspecialchars($row['status_name']) ?>
                </span>
                    </td>
                    <td><?= htmlspecialchars($row['deadline']) ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
      <!-- Table summary -->
      <div class="d-flex justify-content-between align-items-center mt-2 mb-1 px-1 small text-secondary">
        <span>Showing <?= $from ?>–<?= $to ?> of <?= $total ?> items</span>
      </div>
      <!-- Pagination -->
      <?php if ($totalPages > 1): ?>
        <nav class="mt-2">
          <ul class="pagination justify-content-center pagination-lg custom-pagination">
            <li class="page-item<?= $page <= 1 ? ' disabled' : '' ?>">
              <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page-1 ?>" tabindex="-1">&laquo;</a>
            </li>
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <li class="page-item<?= $i == $page ? ' active' : '' ?>">
                <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $i ?>"><?= $i ?></a>
              </li>
            <?php endfor; ?>
            <li class="page-item<?= $page >= $totalPages ? ' disabled' : '' ?>">
              <a class="page-link" href="?search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">&raquo;</a>
            </li>
          </ul>
        </nav>
      <?php endif; ?>
            </div>
          </div>
  <!-- Modal for View Detail -->
  <div class="modal fade" id="viewDetailModal" tabindex="-1" aria-labelledby="viewDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewDetailModalLabel">Content Detail</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <dl class="row mb-0">
            <dt class="col-sm-3">Title</dt>
            <dd class="col-sm-9" id="modalDetailTitle"></dd>
            <dt class="col-sm-3">Description</dt>
            <dd class="col-sm-9" id="modalDetailDescription" style="white-space:pre-wrap;"></dd>
            <dt class="col-sm-3">Status</dt>
            <dd class="col-sm-9" id="modalDetailStatus"></dd>
            <dt class="col-sm-3">Deadline</dt>
            <dd class="col-sm-9" id="modalDetailDeadline"></dd>
            <dt class="col-sm-3">Created At</dt>
            <dd class="col-sm-9" id="modalDetailCreated"></dd>
            <dt class="col-sm-3">Updated At</dt>
            <dd class="col-sm-9" id="modalDetailUpdated"></dd>
          </dl>
        </div>
      </div>
    </div>
  </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var viewDetailModal = document.getElementById('viewDetailModal');
    if (viewDetailModal) {
      viewDetailModal.addEventListener('show.bs.modal', function (event) {
        var button = event.relatedTarget;
        document.getElementById('modalDetailTitle').textContent = button.getAttribute('data-title');
        document.getElementById('modalDetailDescription').textContent = button.getAttribute('data-description');
        document.getElementById('modalDetailStatus').textContent = button.getAttribute('data-status');
        document.getElementById('modalDetailDeadline').textContent = button.getAttribute('data-deadline');
        document.getElementById('modalDetailCreated').textContent = button.getAttribute('data-created');
        document.getElementById('modalDetailUpdated').textContent = button.getAttribute('data-updated');
      });
    }
  });
  </script>
  <style>
    @keyframes pulse {
      0% { box-shadow: 0 0 0 0 rgba(255,193,7,0.7); }
      70% { box-shadow: 0 0 0 10px rgba(255,193,7,0); }
      100% { box-shadow: 0 0 0 0 rgba(255,193,7,0); }
    }
    .animate-pulse .bi-bell-fill {
      color: #ffc107;
      animation: pulse 1.2s infinite;
    }
    .pulse-dot {
      position: absolute;
      top: 8px;
      right: 8px;
      width: 14px;
      height: 14px;
      background: #ffc107;
      border-radius: 50%;
      box-shadow: 0 0 8px 2px #ffc10799;
      animation: pulse 1.2s infinite;
      z-index: 2;
      border: 2px solid #fff;
      outline: 2px solid #1976d2;
      outline-offset: 1px;
    }
    .header-icon-btn {
      border-width: 2px !important;
      border-color: #1976d2 !important;
      background: #f4f8ff !important;
      color: #1976d2 !important;
      width: 40px;
      height: 40px;
      transition: background 0.15s, color 0.15s, box-shadow 0.15s;
      box-shadow: 0 1px 4px 0 rgba(31,38,135,0.06);
    }
    .header-icon-btn:hover, .header-icon-btn:focus {
      background: #e3eaf6 !important;
      color: #1565c0 !important;
      box-shadow: 0 2px 8px 0 rgba(31,38,135,0.10);
      outline: none;
    }
    .header-icon-btn:active {
      background: #dbeafe !important;
      color: #1565c0 !important;
    }
    .header-icon-btn .bi {
      vertical-align: middle;
      display: inline-block;
    }
    /* Custom pagination styles */
    .custom-pagination .page-link {
      border-radius: 1.5rem !important;
      margin: 0 0.15rem;
      font-size: 1.15em;
      min-width: 2.5rem;
      text-align: center;
      color: #1976d2;
      border: 1.5px solid #e3e7ef;
      background: #f6f8fb;
      transition: background 0.15s, color 0.15s, box-shadow 0.15s;
    }
    .custom-pagination .page-link:focus {
      box-shadow: 0 0 0 2px #1976d2aa;
    }
    .custom-pagination .page-item.active .page-link {
      background: linear-gradient(90deg, #1976d2 60%, #42a5f5 100%);
      color: #fff;
      border: none;
      font-weight: 700;
      box-shadow: 0 2px 8px 0 rgba(31,38,135,0.10);
    }
    .custom-pagination .page-item.disabled .page-link {
      color: #b0b8c9;
      background: #f4f6fa;
      border: 1.5px solid #e3e7ef;
    }
    /* Table UI improvements */
    .table-area {
      background: #fff;
      border-radius: 14px;
      box-shadow: 0 2px 16px 0 rgba(31,38,135,0.10);
      border: 2px solid #e3e7ef;
      padding: 1.2rem 1.2rem 1.2rem 1.2rem;
    }
    .table thead th {
      font-size: 1.08rem;
      font-weight: 700;
      background: #e3eaf6;
      color: #1a237e;
      border-bottom: 2px solid #b6c2d6;
      white-space: nowrap;
      text-overflow: ellipsis;
      overflow: hidden;
    }
    .table-striped > tbody > tr:nth-of-type(odd) {
      background-color: #f0f4fa;
    }
    .table-hover tbody tr:hover, .table-hover tbody tr:focus {
      background-color: #dbeafe;
      cursor: pointer;
      transition: background 0.2s;
      outline: 2px solid #1976d2;
    }
    .badge-status {
      font-size: 1em;
      padding: 0.5em 1em;
      border-radius: 1em;
      background: #e3e7ef;
      color: #1565c0;
      font-weight: 600;
    }
    .action-btns {
      min-width: 180px;
      flex-wrap: wrap;
      gap: 0.5rem !important;
    }
    @media (max-width: 767px) {
      .action-btns {
        flex-direction: column !important;
        align-items: stretch !important;
      }
      .table thead th, .table td {
        max-width: 120px !important;
        font-size: 0.98em;
      }
      .table-area {
        padding: 0.5rem 0.2rem 0.5rem 0.2rem;
      }
    }
    .form-control:focus {
      border-color: #1976d2;
      box-shadow: 0 0 0 2px #1976d2aa;
    }
    .btn-view {
      background: #1976d2;
      color: #fff;
      border: none;
    }
    .btn-view:hover, .btn-view:focus {
      background: #1565c0;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 4px 16px 0 rgba(31,38,135,0.13);
    }
    .btn-edit {
      background: #43a047;
      color: #fff;
      border: none;
    }
    .btn-edit:hover, .btn-edit:focus {
      background: #2e7d32;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 4px 16px 0 rgba(67,160,71,0.13);
    }
    .btn-delete {
      background: #e53935;
      color: #fff;
      border: none;
    }
    .btn-delete:hover, .btn-delete:focus {
      background: #b71c1c;
      color: #fff;
      transform: translateY(-2px) scale(1.04);
      box-shadow: 0 4px 16px 0 rgba(229,57,53,0.13);
    }
  </style>
  </body>
</html>