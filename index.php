<?php
require_once('config.php');

// upload file
if (isset($_FILES['upload'])) {
  $targetFile = $DATA_DIR.basename($_FILES['upload']['name']);
  if (move_uploaded_file($_FILES['upload']['tmp_name'], $targetFile)) {
    $file = $targetFile;
    header('Location: '.$_SERVER['SCRIPT_NAME'].'?file='.$targetFile);
  }
  else {
    $error = 'File upload error';
  }
}

// read existing data files
$fileList = [];
$fileIterator = new \RegexIterator(new \FilesystemIterator($DATA_DIR), '/\.json$/i', \RegexIterator::MATCH);
foreach ($fileIterator as $fileInfo) {
  $fileList[] = $DATA_DIR.$fileInfo->getFilename();
}

// get query parameters
$file = filter_input(INPUT_GET, 'file');
$type = filter_input(INPUT_GET, 'type');
$id = filter_input(INPUT_GET, 'id');

// analyze
if ($file && file_exists($file)) {
  ob_start();
  analyze($ANALYZER, realpath($file), $type, $id);
  $result = processResult(ob_get_contents(), $file, $type);
  ob_end_clean();
}

function analyze($ANALYZER, $file, $type, $id) {
  // summary
  echo '<div id="summary"><pre>'.htmlentities(shell_exec('php '.$ANALYZER.' summary "'.$file.'"')).'</pre></div>';

  // instance details
  if ($type) {
    echo '<div id="instance"><pre>'.htmlentities(shell_exec('php '.$ANALYZER.' query -v -f "class='.$type.'" -f "is_root=0" "'.$file.'"')).'</pre></div>';
  }

  // ref path
  if ($id) {
    echo '<div id="path"><pre>'.htmlentities(shell_exec('php '.$ANALYZER.' ref-path -v '.$id.' "'.$file.'"')).'</pre></div>';
  }
}

function processResult($result, $file, $type) {
  // link types in summary table
  $result = preg_replace_callback('/^(\|\s+)([^\s]+)(\s+\|)/m', function($match) use ($file) {
    $type = $match[2];
    return ($type !== 'Type' && !preg_match('/^0x/', $type)) ? $match[1].makeLink($file, $type).$match[3] : $match[1].$type.$match[3];
  }, $result);

  // link types in instance details
  $result = preg_replace_callback('/(\|\s+Class:\s+)([^\s]+)(\s+\|)/m', function($match) use ($file) {
    $type = $match[2];
    return ($type !== 'Type' && !preg_match('/^0x/', $type)) ? $match[1].makeLink($file, $type).$match[3] : $match[1].$type.$match[3];
  }, $result);

  // link instances
  $result = preg_replace_callback('/(0x[a-f0-9]{12})/m', function($match) use ($file, $type) {
    $id = $match[1];
    return makeLink($file, $type, $id);
  }, $result);
  return $result;
}

function makeLink($file, $type, $id=null) {
  return '<a href="'.$_SERVER['SCRIPT_NAME'].'?file='.$file.'&type='.$type.($id ? '&id='.$id : '').($id ? '#path' : ($type ? '#instance' : '')).'">'.($id ? $id : $type).'</a>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>php meminfo analysis</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.20/css/uikit.min.css" />
  <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.20/js/uikit.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/uikit/3.0.0-rc.20/js/uikit-icons.min.js"></script>
</head>
<body>
  <form action="<?= $_SERVER['SCRIPT_NAME']; ?>" method="post" enctype="multipart/form-data">
    <div uk-grid>
      <div>
        <div uk-form-custom="target: true">
          <input type="file" name="upload" accept="application/json,.json">
          <input class="uk-input uk-form-width-medium" type="text" placeholder="UPLOAD FILE" disabled>
        </div>
        <button class="uk-button uk-button-default">Submit</button>
      </div>
      <div>
        <div class="uk-button-group">
          <button class="uk-button uk-button-default" disabled>Select file</button>
          <div class="uk-inline">
            <button class="uk-button uk-button-default uk-padding-remove-horizontal" type="button"><span uk-icon="icon: triangle-down"></span></button>
            <div uk-dropdown="mode: click; boundary: ! .uk-button-group; boundary-align: true;">
              <ul class="uk-nav uk-dropdown-nav">
                <?php foreach($fileList as $curFile) : ?>
                <li><a href="<?= $_SERVER['SCRIPT_NAME']; ?>?file=<?= $curFile; ?>"><?= $curFile; ?></a></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
      </div>
      <div>
        <?php if ($error) : ?>
        <button class="uk-button uk-button-default uk-text-danger" disabled><?= $error; ?></button>
        <?php elseif ($file !== null) : ?>
        <button class="uk-button uk-button-default uk-text-success" disabled><?= $file; ?></button>
        <?php else : ?>
        <button class="uk-button uk-button-default" disabled>No file</button>
        <?php endif; ?>
      </div>
    </div>
  </form>
  <?= $result; ?>
</body>
</html>
