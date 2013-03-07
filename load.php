<?php
$output=array();
$returnVar=0;
chdir("/opt/r2/vlad/aws_s3_glaicer_collector");
exec('git pull 2&gt;&1', $output , $returnVar);
echo "<pre>\n";
echo "return status: $returnVar\n\n";
print_r($output);
echo "</pre>\n";
//cd /opt/r2/vlad/aws_s3_glaicer_collector && git pull

?>