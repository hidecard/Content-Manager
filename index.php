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
      <span class="app-icon">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" width="36" height="36">
          <path d="M39.5563 34.1455V13.8546C39.5563 15.708 36.8773 17.3437 32.7927 18.3189C30.2914 18.916 27.263 19.2655 24 19.2655C20.737 19.2655 17.7086 18.916 15.2073 18.3189C11.1227 17.3437 8.44365 15.708 8.44365 13.8546V34.1455C8.44365 35.9988 11.1227 37.6346 15.2073 38.6098C17.7086 39.2069 20.737 39.5564 24 39.5564C27.263 39.5564 30.2914 39.2069 32.7927 38.6098C36.8773 37.6346 39.5563 35.9988 39.5563 34.1455Z" fill="#fff"></path>
          <path fill-rule="evenodd" clip-rule="evenodd" d="M10.4485 13.8519C10.4749 13.9271 10.6203 14.246 11.379 14.7361C12.298 15.3298 13.7492 15.9145 15.6717 16.3735C18.0007 16.9296 20.8712 17.2655 24 17.2655C27.1288 17.2655 29.9993 16.9296 32.3283 16.3735C34.2508 15.9145 35.702 15.3298 36.621 14.7361C37.3796 14.246 37.5251 13.9271 37.5515 13.8519C37.5287 13.7876 37.4333 13.5973 37.0635 13.2931C36.5266 12.8516 35.6288 12.3647 34.343 11.9175C31.79 11.0295 28.1333 10.4437 24 10.4437C19.8667 10.4437 16.2099 11.0295 13.657 11.9175C12.3712 12.3647 11.4734 12.8516 10.9365 13.2931C10.5667 13.5973 10.4713 13.7876 10.4485 13.8519ZM37.5563 18.7877C36.3176 19.3925 34.8502 19.8839 33.2571 20.2642C30.5836 20.9025 27.3973 21.2655 24 21.2655C20.6027 21.2655 17.4164 20.9025 14.7429 20.2642C13.1498 19.8839 11.6824 19.3925 10.4436 18.7877V34.1275C10.4515 34.1545 10.5427 34.4867 11.379 35.027C12.298 35.6207 13.7492 36.2054 15.6717 36.6644C18.0007 37.2205 20.8712 37.5564 24 37.5564C27.1288 37.5564 29.9993 37.2205 32.3283 36.6644C34.2508 36.2054 35.702 35.6207 36.621 35.027C37.4573 34.4867 37.5485 34.1546 37.5563 34.1275V18.7877ZM41.5563 13.8546V34.1455C41.5563 36.1078 40.158 37.5042 38.7915 38.3869C37.3498 39.3182 35.4192 40.0389 33.2571 40.5551C30.5836 41.1934 27.3973 41.5564 24 41.5564C20.6027 41.5564 17.4164 41.1934 14.7429 40.5551C12.5808 40.0389 10.6502 39.3182 9.20848 38.3869C7.84205 37.5042 6.44365 36.1078 6.44365 34.1455L6.44365 13.8546C6.44365 12.2684 7.37223 11.0454 8.39581 10.2036C9.43325 9.3505 10.8137 8.67141 12.343 8.13948C15.4203 7.06909 19.5418 6.44366 24 6.44366C28.4582 6.44366 32.5797 7.06909 35.657 8.13948C37.1863 8.67141 38.5667 9.3505 39.6042 10.2036C40.6278 11.0454 41.5563 12.2684 41.5563 13.8546Z" fill="#fff"></path>
        </svg>
      </span>
      <span class="app-title">Content Hub</span>
    </div>
    <div class="header-action d-flex align-items-center gap-2">
      <a class="btn btn-primary d-inline-flex align-items-center" href="new.php">
        <i class="bi bi-plus-circle me-1"></i> New Content
      </a>
      <a href="settings.php" class="btn btn-light border-0 p-2" title="Settings">
        <i class="bi bi-gear-fill" style="font-size:1.5em;"></i>
      </a>
    </div>
  </div>
  <?php if (count($notify_contents) > 0): ?>
    <div class="container-fluid mt-3" style="max-width:900px;">
      <div class="alert alert-warning d-flex align-items-center gap-2 shadow-sm position-relative animate-pulse" role="alert" style="font-size:1.15em;">
        <span class="position-relative">
          <i class="bi bi-bell-fill" style="font-size:1.5em;"></i>
          <span class="pulse-dot"></span>
        </span>
        <div>
          <strong>Deadline Reminder:</strong>
          <?php foreach ($notify_contents as $n): ?>
            <div>
              <b><?= htmlspecialchars($n['title']) ?></b> - deadline: <span class="text-danger fw-bold"><?= htmlspecialchars($n['deadline']) ?></span> <span class="badge badge-status ms-2">Status: <?= htmlspecialchars($n['status_name']) ?></span>
            </div>
          <?php endforeach; ?>
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
          <tr class="table-title-row">
            <th colspan="5">Content List</th>
          </tr>
          <tr>
            <th scope="col" style="min-width: 200px;">Content Title</th>
            <th scope="col" style="min-width: 200px;">Description</th>
            <th scope="col" style="min-width: 160px;">Actions</th>
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
              <td class="fw-semibold text-dark"><?= htmlspecialchars($row['title']) ?></td>
              <td style="max-width:320px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                <?php
                  $desc = htmlspecialchars($row['description']);
                  if (mb_strlen($desc) > 60) {
                      echo mb_substr($desc, 0, 60) . '... ';
                  } else {
                      echo $desc;
                  }
                ?>
              </td>
              <td>
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
      top: 0;
      right: -7px;
      width: 12px;
      height: 12px;
      background: #ffc107;
      border-radius: 50%;
      box-shadow: 0 0 8px 2px #ffc10799;
      animation: pulse 1.2s infinite;
      z-index: 2;
    }
    .header-action .btn-light:hover {
      background: #e3eaf6;
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
  </style>
</body>
</html>