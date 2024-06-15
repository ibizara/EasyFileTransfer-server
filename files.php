<?php
// User Configuration Options
$valid_username = 'user';
$valid_password = 'password';
$uploadDir = '/uploads/';

// Secure session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.use_strict_mode', 1);

session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}

$valid_password_hash = password_hash($valid_password, PASSWORD_DEFAULT);
$files = [];

// Function to convert shorthand notation to bytes
function convertToBytes($value) {
    $value = trim($value);
    $last = strtolower($value[strlen($value) - 1]);
    $value = (int)$value;
    switch ($last) {
        case 'g':
            $value *= 1024;
        case 'm':
            $value *= 1024;
        case 'k':
            $value *= 1024;
    }
    return $value;
}

// Get upload size limit from PHP configuration
$upload_max_filesize = convertToBytes(ini_get('upload_max_filesize'));
$post_max_size = convertToBytes(ini_get('post_max_size'));
$max_upload_size = min($upload_max_filesize, $post_max_size);

// Get PHP configuration values
$max_execution_time = ini_get('max_execution_time');
$max_input_time = ini_get('max_input_time');

// Handle logout
if (isset($_GET['logout']) && $_GET['logout'] == 'true') {
    session_unset();
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (strtolower($username) === $valid_username && password_verify($password, $valid_password_hash)) {
        $_SESSION['loggedin'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = 'Oops! The username or password you entered doesn\'t match our records. Please try again.';
    }
}

// Check if user is logged in
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['files'])) {
        $response = '';
        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['size'][$key] > $max_upload_size) {
                $response .= "Error: File $name exceeds the maximum upload size limit.<br>";
            } else {
                $targetFile = $uploadDir . basename($name);
                if (move_uploaded_file($_FILES['files']['tmp_name'][$key], $targetFile)) {
                    $response .= "File $name uploaded successfully.<br>";
                } else {
                    $response .= "Error uploading file $name.<br>";
                }
            }
        }
        echo $response;
        exit;
    }

    // Handle file download
    if (isset($_GET['download'])) {
        $file = basename($_GET['download']);
        $filePath = $uploadDir . $file;

        if (file_exists($filePath)) {
            $mimeType = mime_content_type($filePath);
            $fileSize = filesize($filePath);

            header('Content-Description: File Transfer');
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename=' . basename($filePath));
            header('Expires: 0');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Content-Length: ' . $fileSize);

            $chunkSize = 8192; // 8KB chunks
            $handle = fopen($filePath, 'rb');
            if ($handle === false) {
                echo "Error opening file.";
                exit;
            }

            while (!feof($handle)) {
                $buffer = fread($handle, $chunkSize);
                echo $buffer;
                ob_flush();
                flush();
            }

            fclose($handle);
            exit;
        } else {
            echo "File not found.";
        }
    }

    // Handle file deletion
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete'])) {
        ob_start(); // Start output buffering
        $fileToDelete = basename($_POST['delete']);
        $filePath = $uploadDir . $fileToDelete;

        if (file_exists($filePath)) {
            unlink($filePath);
            $response = ['status' => 'success'];
        } else {
            $response = ['status' => 'error', 'message' => 'File not found'];
        }
        ob_end_clean(); // Clean (erase) the output buffer and turn off output buffering
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Fetch files for AJAX request
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        $files = array_filter(array_diff(scandir($uploadDir), array('.', '..')), function($file) use ($uploadDir) {
            return pathinfo($file, PATHINFO_EXTENSION) !== 'php';
        });
        echo json_encode(array_values($files));
        exit;
    }

    // Fetch files for regular request (non-AJAX)
    $files = array_filter(array_diff(scandir($uploadDir), array('.', '..')), function($file) use ($uploadDir) {
        return pathinfo($file, PATHINFO_EXTENSION) !== 'php';
    });
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>File Upload</title>
    <link href="/css/bootstrap.min.css" rel="stylesheet">
    <script src="/js/jquery-3.5.1.min.js"></script>
    <script src="/js/jquery.dataTables.min.js"></script>
    <link rel="stylesheet" href="/css/jquery.dataTables.min.css">
    <style>
        .progress {
            display: none;
        }
        .file-input {
            padding-bottom: 36px;
        }
        #logout {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        #drop-area {
            border: 2px dashed #ccc;
            border-radius: 5px;
            padding: 20px;
            text-align: center;
            margin-bottom: 20px;
        }
        #drop-area.highlight {
            border-color: purple;
        }
    </style>
</head>
<body>
<div class="container mt-5">
    <?php if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']): ?>
        <h2>Login</h2>
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" class="form-control" id="username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" class="form-control" id="password" required>
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center">
            <h2>Upload Files</h2>
            <a href="<?= $_SERVER['PHP_SELF'] ?>?logout=true" class="btn btn-danger">Logout</a>
        </div>
        <div id="drop-area">
            <p>Drag & drop files here or click to select files</p>
            <input type="file" id="fileElem" multiple class="file-input" style="display:none;">
            <button type="button" class="btn btn-primary" id="fileSelect">Select Files</button>
        </div>
        <div class="progress mt-3">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div id="status" class="mt-3"></div>
        <div id="timeRemaining" class="mt-3"></div>
        <h2 class="mt-5">Uploaded Files</h2>
        <table id="filesTable" class="display">
            <thead>
                <tr>
                    <th>Filename</th>
                    <th>Size (KB)</th>
                    <th>Last Modified</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $file): ?>
                    <tr>
                        <td>
                            <a href="javascript:void(0);" class="download-link" data-file="<?= $file ?>"><?= $file ?></a>
                        </td>
                        <td><?php echo number_format(filesize($uploadDir . $file) / 1024, 2); ?></td>
                        <td><?php echo date('Y-m-d H:i:s', filemtime($uploadDir . $file)); ?></td>
                        <td>
                            <a href="javascript:void(0);" class="delete-link btn btn-danger btn-sm" data-file="<?= $file ?>">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    <?php if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']): ?>
    var maxExecutionTime = <?= $max_execution_time ?>;
    var maxInputTime = <?= $max_input_time ?>;
    $('#filesTable').DataTable({
        "pageLength": 25
    });

    function uploadFiles(formData) {
        var startTime = new Date().getTime();
        $.ajax({
            xhr: function() {
                var xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        $('.progress').css('display', 'flex');
                        var percentComplete = e.loaded / e.total * 100;
                        $('#progressBar').css('width', percentComplete + '%');
                        $('#progressBar').attr('aria-valuenow', percentComplete);
                        $('#progressBar').text(Math.round(percentComplete) + '%');

                        var elapsed = (new Date().getTime() - startTime) / 1000; // in seconds
                        var uploadSpeed = e.loaded / elapsed; // bytes per second
                        var timeRemaining = (e.total - e.loaded) / uploadSpeed; // seconds

                        if (timeRemaining > maxExecutionTime || timeRemaining > maxInputTime) {
                            $('#status').html('<div class="alert alert-warning">Warning: Estimated time exceeds server limits. Upload will continue, but may fail.</div>');
                        }
                        $('#timeRemaining').text('Time remaining: ' + Math.round(timeRemaining) + ' seconds');
                    }
                }, false);
                return xhr;
            },
            type: 'POST',
            url: '<?= $_SERVER['PHP_SELF'] ?>',
            data: formData,
            contentType: false,
            processData: false,
            beforeSend: function() {
                $('#progressBar').css('width', '0%');
                $('#progressBar').text('0%');
                $('#status').text('');
            },
            success: function(response) {
                $('#status').html('<div class="alert alert-success">' + response + '</div>');
                $('#progressBar').css('width', '100%');
                $('#progressBar').text('100%');
                setTimeout(function() {
                    location.reload();
                }, 2000);
            },
            error: function(xhr, status, error) {
                $('#status').html('<div class="alert alert-danger">Error uploading files: ' + error + '</div>');
            }
        });
    }

    function handleFiles(files) {
        var maxUploadSize = <?= $max_upload_size ?>;
        for (var i = 0; i < files.length; i++) {
            if (files[i].size > maxUploadSize) {
                $('#status').html('<div class="alert alert-danger">Error: File ' + files[i].name + ' exceeds the maximum upload size limit.</div>');
                return;
            }
        }

        var formData = new FormData();
        for (var i = 0; i < files.length; i++) {
            formData.append('files[]', files[i]);
        }
        uploadFiles(formData);
    }

    function downloadFile(fileName, fileSizeKB) {
        $('.progress').css('display', 'flex');
        $('#progressBar').css('width', '0%');
        $('#progressBar').text('0%');

        const fileSizeBytes = fileSizeKB * 1024; // Convert KB to bytes

        fetch('?download=' + encodeURIComponent(fileName))
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok.');
                }

                let loaded = 0;

                return new Response(
                    new ReadableStream({
                        start(controller) {
                            const reader = response.body.getReader();

                            function read() {
                                reader.read().then(({ done, value }) => {
                                    if (done) {
                                        controller.close();
                                        return;
                                    }
                                    loaded += value.byteLength;
                                    const percentComplete = (loaded / fileSizeBytes) * 100;
                                    $('#progressBar').css('width', percentComplete + '%');
                                    $('#progressBar').attr('aria-valuenow', percentComplete);
                                    $('#progressBar').text(Math.round(percentComplete) + '%');
                                    controller.enqueue(value);
                                    read();
                                }).catch(error => {
                                    console.error(error);
                                    controller.error(error);
                                });
                            }

                            read();
                        }
                    })
                );
            })
            .then(response => response.blob())
            .then(blob => {
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.style.display = 'none';
                a.href = url;
                a.download = fileName;
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
                $('.progress').css('display', 'none');
                $('#progressBar').css('width', '0%');
                $('#progressBar').text('0%');
            })
            .catch(error => {
                console.error('Download failed:', error);
                $('#status').html('<div class="alert alert-danger">Error downloading file.</div>');
                $('.progress').css('display', 'none');
            });
    }

    function deleteFile(fileName) {
        $.ajax({
            type: 'POST',
            url: '<?= $_SERVER['PHP_SELF'] ?>',
            data: { delete: fileName },
            success: function(response) {
                if (response.status === 'success') {
                    location.reload();
                } else {
                    $('#status').html('<div class="alert alert-danger">Error deleting file: ' + response.message + '</div>');
                }
            },
            error: function(xhr, status, error) {
                $('#status').html('<div class="alert alert-danger">Error deleting file: ' + error + '</div>');
            }
        });
    }

    $('.download-link').on('click', function (e) {
        e.preventDefault();
        var fileName = $(this).data('file');
        var fileSizeKB = parseFloat($(this).closest('tr').find('td:nth-child(2)').text().replace(/,/g, ''));
        downloadFile(fileName, fileSizeKB);
    });

    $('.delete-link').on('click', function (e) {
        e.preventDefault();
        var fileName = $(this).data('file');
        deleteFile(fileName);
    });

    // File selection
    document.getElementById('fileSelect').addEventListener('click', function() {
        document.getElementById('fileElem').click();
    });

    document.getElementById('fileElem').addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag and drop functionality
    var dropArea = document.getElementById('drop-area');

    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.addEventListener(eventName, unhighlight, false);
    });

    dropArea.addEventListener('drop', handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dropArea.classList.add('highlight');
    }

    function unhighlight(e) {
        dropArea.classList.remove('highlight');
    }

    function handleDrop(e) {
        var dt = e.dataTransfer;
        var files = dt.files;

        handleFiles(files);
    }
    <?php endif; ?>
});
</script>
</body>
</html>
