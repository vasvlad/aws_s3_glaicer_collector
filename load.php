<?php
$output=array();
$returnVar=0;
chdir("/opt/r2/vlad/aws_s3_glaicer_collector");
//chdir("/var/www/aws_s3_glaicer");
exec('git pull ', $output , $returnVar);
echo "<pre>\n";
echo "return status: $returnVar\n\n";
print_r($output);
echo "</pre>\n";

?>
