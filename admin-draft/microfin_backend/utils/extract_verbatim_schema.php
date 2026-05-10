<?php
$dumpFile = 'c:/xampp/htdocs/admin-draft-withmobile/admin-draft/microfin_web/docs/Dump20260420.sql';
$outputFile = 'c:/xampp/htdocs/admin-draft-withmobile/admin-draft/microfin_web/docs/extracted_schema.sql';

$content = file_get_contents($dumpFile);
// Match CREATE TABLE blocks including the ENGINE and character set at the end
preg_match_all('/DROP TABLE IF EXISTS `(.*?)`;.*?CREATE TABLE `\1` \((.*?)\) ENGINE=InnoDB(.*?);/s', $content, $matches);

$output = "SET FOREIGN_KEY_CHECKS=0;\n\n";
foreach ($matches[0] as $fullMatch) {
    $output .= $fullMatch . "\n\n";
}
$output .= "SET FOREIGN_KEY_CHECKS=1;\n";

file_put_contents($outputFile, $output);
echo "Extracted " . count($matches[0]) . " tables verbatim.\n";
