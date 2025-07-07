<?php
// Database connection
$dbFile = 'database.db';
try {
    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
}
// Fetch statuses for radio buttons
$statusStmt = $pdo->query('SELECT status_id, status_name FROM Status');
$statuses = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['contentTitle'] ?? '');
    $description = trim($_POST['contentDescription'] ?? '');
    $deadline = $_POST['contentDate'] ?? null;
    $status_id = $_POST['status'] ?? null;
    if ($title && $status_id && $deadline) {
        $stmt = $pdo->prepare('INSERT INTO Content (title, description, status_id, deadline) VALUES (?, ?, ?, ?)');
        $stmt->execute([$title, $description, $status_id, $deadline]);
        header('Location: index.php');
        exit;
    } else {
        $error = 'Please fill in all required fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Content</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            font-family: 'Segoe UI', 'Inter', 'Noto Sans', Arial, sans-serif;
            background: #f6f8fb;
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
        .main-content {
            max-width: 95vw;
            margin: 2.5rem auto 0 auto;
            padding: 0 2vw;
            display: flex;
            flex-direction: column;
            gap: 2.2rem;
            align-items: center;
        }
        .form-area {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 2px 16px 0 rgba(31,38,135,0.10);
            border: 2px solid #d0d7e3;
            padding: 2.2rem 2.2rem 1.2rem 2.2rem;
            max-width: 95%;
            width: 100%;
            margin: 0 auto;
        }
        .form-title {
            font-size: 1.25rem;
            font-weight: 900;
            color: #1976d2;
            margin-bottom: 1.5rem;
            letter-spacing: 0.5px;
            text-align: center;
        }
        label.form-label {
            font-weight: 700;
            color: #1976d2;
            margin-bottom: 0.3rem;
        }
        input.form-control, textarea.form-control, select.form-select {
            border-radius: 8px;
            border: 2px solid #d0d7e3;
            font-size: 1.08em;
            margin-bottom: 1.2rem;
            background: #f6f8fb;
            box-shadow: none;
            transition: border 0.15s;
        }
        input.form-control:focus, textarea.form-control:focus, select.form-select:focus {
            border: 2px solid #1976d2;
            background: #fff;
            outline: none;
        }
        textarea.form-control {
            resize: vertical;
            min-height: 180px;
            max-height: 500px;
        }
        .form-actions {
            display: flex;
            gap: 1.2rem;
            justify-content: flex-end;
            margin-top: 1.5rem;
        }
        .btn {
            border-radius: 10px;
            font-weight: 700;
            font-size: 1.1em;
            padding: 0.6em 1.4em;
            box-shadow: 0 2px 8px 0 rgba(31,38,135,0.04);
            transition: background 0.15s, color 0.15s, box-shadow 0.15s, transform 0.1s;
        }
        .btn-save {
            background: #1976d2;
            color: #fff;
            border: none;
        }
        .btn-save:hover, .btn-save:focus {
            background: #1565c0;
            color: #fff;
            transform: translateY(-2px) scale(1.04);
            box-shadow: 0 4px 16px 0 rgba(31,38,135,0.13);
        }
        .btn-cancel {
            background: #e53935;
            color: #fff;
            border: none;
        }
        .btn-cancel:hover, .btn-cancel:focus {
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
            .form-area {
                padding: 1.2rem 0.5rem 1rem 0.5rem;
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
        <div class="header-action">
            <a class="btn btn-primary d-inline-flex align-items-center" href="new.php">
                <i class="bi bi-plus-circle me-1"></i> New Content
            </a>
        </div>
    </div>
    <div class="main-content">
        <div class="form-area">
            <div class="form-title">New Content</div>
            <?php if ($error): ?>
                <div class="alert alert-danger"> <?= htmlspecialchars($error) ?> </div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="contentTitle" class="form-label">Content Title</label>
                    <input type="text" class="form-control" id="contentTitle" name="contentTitle" placeholder="Enter content title" required>
                </div>
                <div class="mb-3">
                    <label for="contentDescription" class="form-label">Content Description</label>
                    <textarea class="form-control" id="contentDescription" name="contentDescription" rows="10"></textarea>
                </div>
                <div class="mb-3">
                    <label for="contentDate" class="form-label">Deadline</label>
                    <input type="date" class="form-control" id="contentDate" name="contentDate" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">Status</label>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($statuses as $status): ?>
                        <div class="form-check flex-grow-1">
                            <input class="form-check-input" type="radio" name="status" id="status<?= $status['status_id'] ?>" value="<?= $status['status_id'] ?>" <?= $status['status_name'] === 'Draft' ? 'checked' : '' ?> required>
                            <label class="form-check-label" for="status<?= $status['status_id'] ?>"> <?= htmlspecialchars($status['status_name']) ?> </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-actions">
                    <button class="btn btn-save px-4 fw-bold" type="submit">
                        <i class="bi bi-save me-1"></i> Save
                    </button>
                    <a href="index.php" class="btn btn-cancel px-4">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 