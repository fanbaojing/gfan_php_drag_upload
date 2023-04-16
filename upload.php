<?php
/*
 * @app name: GFAN_PHP_DRAG_UPLOAD - PHP File for uploading file purposes.
 * @author: Gavin FAN (BFANGFAN) <fbaojing@gmail.com>
 * @use: Including these comments while using or modifying
 * @version: 1.0
 * @year: 2023
*/

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))
{
  if ($_GET['action'] === 'getDirs')
  {
    $path = isset($_GET['path']) ? $_GET['path'] : '.';
    $dirs = glob($path . '*', GLOB_ONLYDIR);
    echo json_encode($dirs);
    exit();
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
  $targetFolder = $_POST['folder'] ? $_POST['folder'] : '.';
  if (!is_dir($targetFolder))
  {
    if (!mkdir($targetFolder, 0755, true))
    {
      http_response_code(500);
      echo "Unable to create target folder.";
      exit();
    }
  }

  if (strlen($_POST['folder']) >= 1) { $targetFile = $_POST['folder'] . '/' . $_POST['fileName']; } else { $targetFile = $_POST['fileName']; }

  $action = $_POST['action'];

  if ($action === 'createAndWriteFile')
  {
    $fileContent = $_POST['fileContent'];
    $fileCreated = file_put_contents($targetFile, $fileContent);

    if ($fileCreated !== false) { echo "File uploaded successfully"; } else { http_response_code(400); echo "Error uploading PHP file. Check file permissions and server restrictions."; }
  }
  elseif ($action === 'uploadFile')
  {
    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetFile))
    {
      echo "File uploaded successfully";
    }
    else
    {
      http_response_code(400);
      $errorCode = $_FILES['file']['error'];
      $errorMessage = "Error uploading file. Error code: $errorCode. ";
    }
  }
  else
  {
    http_response_code(400);
    echo "Invalid action specified";
  }
  exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>File Upload</title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;700;800&display=swap');
    * { padding: 0; margin: 0; box-sizing: border-box; }
    body { font-family: 'Open Sans', sans-serif; }
    #form { width: calc(100% - 20px); margin-left: 10px; }
    #form h2 { margin-top: 10px; margin-bottom: 10px; }
    #uploadArea
    {
      width: 100%;
      height: 300px;
      border: 2px dashed #ccc;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 20px;
      cursor: pointer;
      margin-bottom: 10px;
    }
    #folderInput { width: 100%; padding: 10px; margin-bottom: 10px; }
    #uploadProgress { width: 100%; }
    
    #folderList
    {
      background-color: #f8f8f8;
      border: 1px solid #ccc;
      padding: 8px;
      max-height: 200px;
      overflow-y: auto;
      margin-bottom: 10px;
    }
    #folderList div { padding: 4px 8px; margin-bottom: 2px; }
    #folderList div:hover { background-color: #ddd; }
  </style>
</head>
<body>
  <div id='form'>
    <h2>GFAN PHP Upload</h2>
    <input type="text" id="folderInput" placeholder="Folder name (leave blank for current directory)">
    <div id="folderList" style="display: none;"></div>
    <div class="upload-area" id="uploadArea">Drag Files Here</div>
    <progress id="uploadProgress" value="0" max="100" style="display:none;"></progress>
  </div>
  <script>
    const uploadArea = document.getElementById('uploadArea');
    const folderInput = document.getElementById('folderInput');
    const uploadProgress = document.getElementById('uploadProgress');

    uploadArea.addEventListener('dragover', (e) => {
      e.preventDefault();
      e.stopPropagation();
      uploadArea.style.backgroundColor = 'rgba(0, 255, 0, 0.2)';
    });

    uploadArea.addEventListener('dragleave', (e) => {
      e.preventDefault();
      e.stopPropagation();
      uploadArea.style.backgroundColor = 'transparent';
    });

    uploadArea.addEventListener('drop', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      uploadArea.style.backgroundColor = 'transparent';
      const files = e.dataTransfer.files;
      for (let i = 0; i < files.length; i++) {
        await uploadFile(files[i]);
      }
    });

    async function uploadFile(file)
    {
      return new Promise(async (resolve, reject) => {
        const formData = new FormData();
        const fileExtension = file.name.split('.').pop().toLowerCase();

        if (fileExtension === 'php') {
          const fileContent = await new Promise((resolve) => {
            const reader = new FileReader();
            reader.onload = (event) => resolve(event.target.result);
            reader.readAsText(file);
          });
          formData.append('action', 'createAndWriteFile');
          formData.append('fileContent', fileContent);
        } else {
          formData.append('action', 'uploadFile');
          formData.append('file', file);
        }

        formData.append('fileName', file.name);
        formData.append('folder', folderInput.value);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', 'upload.php', true);

        xhr.upload.onprogress = (event) => {
          if (event.lengthComputable) {
            uploadProgress.style.display = 'block';
            const percentCompleted = (event.loaded / event.total) * 100;
            uploadProgress.value = percentCompleted;
          }
        };

        xhr.onload = () => {
          uploadProgress.style.display = 'none';
          if (xhr.status === 200) {
            alert('File uploaded successfully');
            resolve();
          } else {
            alert(`Error uploading file: ${xhr.responseText}`);
            reject();
          }
        };

        xhr.send(formData);
      });
    }
    
    function createDirListItem(dir)
    {
      const div = document.createElement('div');
      const dirName = dir.split('/').pop();
      div.textContent = dirName;
      div.style.cursor = 'pointer';
      div.dataset.path = dir;

      div.addEventListener('click', () => {
        folderInput.value = div.dataset.path + '/';
        updateFolderList();
      });

      return div;
    }

    async function updateFolderList()
    {
      const path = folderInput.value;
      try
      {
        const response = await fetch(`?action=getDirs&path=${encodeURIComponent(path)}`);
        if (response.ok)
        {
          const dirs = await response.json();
          let matchingDirs = dirs;

          if (folderInput.value.length >= 1) { matchingDirs = dirs.filter((dir) => dir.startsWith(folderInput.value)); }

          folderList.innerHTML = '';
          matchingDirs.forEach( (dir) => { folderList.appendChild(createDirListItem(dir)); } );
          folderList.style.display = 'block';
        } else { throw new Error('Error fetching directories'); }
      }
      catch (error)
      {
        console.error('Error fetching directories:', error);
      }
    }

    folderInput.addEventListener('focus', updateFolderList);
    folderInput.addEventListener('input', updateFolderList);
  </script>
</body>
</html>
