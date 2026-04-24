<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["role"] !== "patient") {
    header("Location: login.php");
    exit();
}

$patientId = intval($_SESSION["user_id"]);
$message   = "";
$isError   = false;

/* =========================
   HANDLE FILE UPLOAD
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_FILES["document"])) {

    $file = $_FILES["document"];

    if ($file["error"] === 0) {

        $allowedTypes = ["pdf", "doc", "docx", "txt"];
        $fileName     = basename($file["name"]);
        $fileExt      = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $fileSize     = $file["size"];

        if (!in_array($fileExt, $allowedTypes)) {
            $message = "Invalid file type. Only PDF, DOC, DOCX and TXT are allowed.";
            $isError = true;
        } elseif ($fileSize > 10 * 1024 * 1024) {
            $message = "File too large. Maximum allowed size is 10MB.";
            $isError = true;
        } else {
            $newFileName = uniqid() . "_" . preg_replace('/[^a-zA-Z0-9._-]/', '_', $fileName);
            $uploadDir   = "../static/uploads/";
            $uploadPath  = $uploadDir . $newFileName;

            if (move_uploaded_file($file["tmp_name"], $uploadPath)) {
                $stmt = $conn->prepare("
                    INSERT INTO uploads (patient_id, file_name, file_path)
                    VALUES (:pid, :fname, :fpath)
                ");
                $stmt->execute([
                    "pid"   => $patientId,
                    "fname" => $fileName,
                    "fpath" => $uploadPath
                ]);
                $message = "\"" . htmlspecialchars($fileName) . "\" uploaded successfully!";
            } else {
                $message = "Upload failed. Please check folder permissions and try again.";
                $isError = true;
            }
        }
    } else {
        $message = "A file error occurred. Please try again.";
        $isError = true;
    }
}

/* =========================
   FETCH UPLOADED FILES
========================= */
$stmt = $conn->prepare("
    SELECT * FROM uploads
    WHERE patient_id = :pid
    ORDER BY uploaded_at DESC
");
$stmt->execute(["pid" => $patientId]);
$files     = $stmt->fetchAll(PDO::FETCH_ASSOC);
$fileCount = count($files);

/* =========================
   HELPERS
========================= */
function fileTypeIcon(string $ext): array {
    return match($ext) {
        'pdf'         => ['fa-file-pdf',  'pdf'],
        'doc', 'docx' => ['fa-file-word', 'docx'],
        'txt'         => ['fa-file-lines','txt'],
        default       => ['fa-file',      'other'],
    };
}

function formatBytes(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Uploads — ApexCare</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../static/patient_sidebar.css">
    <link rel="stylesheet" href="../static/puploads.css">
</head>
<body>

<div class="layout">
    <?php include "../static/includes/patient_sidebar.php"; ?>

    <main class="content">

        <!-- ── Page Header ─────────────────────────────── -->
        <div class="page-header">
            <div class="page-header-left">
                <h1>
                    <span class="header-icon"><i class="fas fa-cloud-arrow-up"></i></span>
                    My Uploads
                </h1>
                <p class="page-header-sub">Upload and manage your medical documents securely</p>
            </div>
            <div class="header-date">
                <i class="fas fa-calendar-day"></i>
                <?php echo date('d M Y'); ?>
            </div>
        </div>

        <!-- ── Alert ───────────────────────────────────── -->
        <?php if (!empty($message)): ?>
            <div class="upload-alert <?php echo $isError ? 'error' : 'success'; ?>">
                <i class="fas fa-<?php echo $isError ? 'circle-exclamation' : 'circle-check'; ?>"></i>
                <?php echo $isError ? htmlspecialchars($message) : $message; ?>
            </div>
        <?php endif; ?>

        <!-- ── Two-column layout ───────────────────────── -->
        <div class="uploads-grid">

            <!-- LEFT: Upload card -->
            <div class="u-card">
                <div class="u-card-head">
                    <div class="u-card-head-left">
                        <span class="u-card-icon teal"><i class="fas fa-arrow-up-from-bracket"></i></span>
                        <div>
                            <div class="u-card-title">Upload Document</div>
                            <div class="u-card-sub">PDF, DOC, DOCX or TXT · max 10MB</div>
                        </div>
                    </div>
                </div>
                <div class="u-card-body">
                    <form method="POST" enctype="multipart/form-data" id="uploadForm">

                        <!-- Dropzone -->
                        <div class="dropzone" id="dropzone">
                            <input
                                type="file"
                                name="document"
                                id="fileInput"
                                accept=".pdf,.doc,.docx,.txt"
                                required
                                onchange="onFileSelected(this)"
                            >
                            <div class="dropzone-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                            <div class="dropzone-title">Drop your file here</div>
                            <div class="dropzone-sub">or <span>click to browse</span> from your device</div>
                        </div>

                        <!-- Selected file preview -->
                        <div class="selected-file-strip" id="fileStrip">
                            <i class="fas fa-file-circle-check"></i>
                            <span class="selected-file-name" id="selectedFileName"></span>
                            <span id="selectedFileSize" style="font-size:11px;color:var(--text-muted);font-family:'DM Mono',monospace;white-space:nowrap;"></span>
                        </div>

                        <!-- Allowed type chips -->
                        <div class="type-hints">
                            <span class="type-chip"><i class="fas fa-file-pdf" style="color:#e53e3e;"></i> PDF</span>
                            <span class="type-chip"><i class="fas fa-file-word" style="color:var(--navy);"></i> DOC</span>
                            <span class="type-chip"><i class="fas fa-file-word" style="color:var(--navy);"></i> DOCX</span>
                            <span class="type-chip"><i class="fas fa-file-lines"></i> TXT</span>
                        </div>

                        <button type="submit" class="btn-upload">
                            <i class="fas fa-cloud-arrow-up"></i>
                            Upload Document
                        </button>

                    </form>
                </div>
            </div>

            <!-- RIGHT: Uploaded files list -->
            <div class="u-card">
                <div class="u-card-head">
                    <div class="u-card-head-left">
                        <span class="u-card-icon navy"><i class="fas fa-folder-open"></i></span>
                        <div>
                            <div class="u-card-title">Uploaded Documents</div>
                            <div class="u-card-sub">Your shared medical files</div>
                        </div>
                    </div>
                    <span class="file-count-pill"><?php echo $fileCount; ?> file<?php echo $fileCount !== 1 ? 's' : ''; ?></span>
                </div>
                <div class="u-card-body" style="padding: <?php echo $fileCount > 0 ? '16px' : '0'; ?>;">

                    <?php if ($fileCount > 0): ?>
                        <div class="file-list">
                            <?php foreach ($files as $file):
                                $ext     = strtolower(pathinfo($file["file_name"], PATHINFO_EXTENSION));
                                [$icon, $cls] = fileTypeIcon($ext);

                                /* Try to get file size from disk */
                                $diskSize = '';
                                if (file_exists($file["file_path"])) {
                                    $diskSize = formatBytes(filesize($file["file_path"]));
                                }

                                $uploadedDate = date('d M Y', strtotime($file["uploaded_at"]));
                                $uploadedTime = date('H:i', strtotime($file["uploaded_at"]));
                            ?>
                            <a href="<?php echo htmlspecialchars($file["file_path"]); ?>"
                               target="_blank"
                               rel="noopener"
                               class="file-item">
                                <div class="file-type-icon <?php echo $cls; ?>">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="file-info">
                                    <div class="file-name"><?php echo htmlspecialchars($file["file_name"]); ?></div>
                                    <div class="file-meta">
                                        <span><?php echo $uploadedDate; ?></span>
                                        <span class="file-meta-dot"></span>
                                        <span><?php echo $uploadedTime; ?></span>
                                        <?php if ($diskSize): ?>
                                            <span class="file-meta-dot"></span>
                                            <span><?php echo $diskSize; ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="file-open-btn" title="Open file">
                                    <i class="fas fa-arrow-up-right-from-square"></i>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon"><i class="fas fa-folder-open"></i></div>
                            <p>No documents uploaded yet. Use the form to share files with your doctor.</p>
                        </div>
                    <?php endif; ?>

                </div>
            </div>

        </div>

    </main>
</div>

<script>
    /* ── Dropzone drag/drop visual ── */
    const dropzone = document.getElementById('dropzone');

    ['dragenter', 'dragover'].forEach(evt => {
        dropzone.addEventListener(evt, e => {
            e.preventDefault();
            dropzone.classList.add('drag-over');
        });
    });

    ['dragleave', 'drop'].forEach(evt => {
        dropzone.addEventListener(evt, e => {
            e.preventDefault();
            dropzone.classList.remove('drag-over');
            if (evt === 'drop' && e.dataTransfer.files.length) {
                document.getElementById('fileInput').files = e.dataTransfer.files;
                onFileSelected(document.getElementById('fileInput'));
            }
        });
    });

    /* ── Show selected file name ── */
    function onFileSelected(input) {
        const strip    = document.getElementById('fileStrip');
        const nameEl   = document.getElementById('selectedFileName');
        const sizeEl   = document.getElementById('selectedFileSize');

        if (input.files && input.files[0]) {
            const f    = input.files[0];
            const kb   = f.size / 1024;
            const size = kb < 1024
                ? Math.round(kb) + ' KB'
                : (kb / 1024).toFixed(1) + ' MB';

            nameEl.textContent = f.name;
            sizeEl.textContent = size;
            strip.classList.add('visible');
        } else {
            strip.classList.remove('visible');
        }
    }
</script>

</body>
</html>