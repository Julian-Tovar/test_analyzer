<?php
// In kow-bta.php, bta stands for Behat Test Analyzer.

require("lib.php");
require("bta.php");

// Parse command line arguments

$sn = $argv[0]; // Script name.
$args = array_slice($argv, 1);
$verbose = false;
$csv = false;
$csv_file = null;
$urls_file = null;
$show_all = false;
global $tmp_dir;

$help_flags = ['--help', '-h', '--usage', '--man'];
if (!empty(array_intersect($help_flags, $args))) {
    print_description();
    exit(0);
}

$user_option = '--user';
$branch_option = '--branch';
$from_option = '--from';
$to_option = '--to';

$csv_flag = '--csv';
$verbose_flag = '--verbose';
$show_all_flag = '--show-all';

$other_flags = [$csv_flag, $verbose_flag, $show_all_flag];
$optargs = [$user_option, $branch_option, $from_option, $to_option];
$allopts = array_merge($help_flags, $other_flags, $optargs);

if (in_array($verbose_flag, $args)){
    $verbose = true;
}

// Check that the used flags or options are supported
for ($idx = 0; $idx < count($args); $idx++) {
    if (substr($args[$idx], 0, 1) == '-' && !in_array($args[$idx], $allopts)) {
        echo "Unknown option or flag " . $args[$idx] . "\n\n";
        print_help();
        exit(1);
    }
}

$user = in_array($user_option, $argv) ? get_value_from_opt($user_option, $argv) : null;
$branch = in_array($branch_option, $argv) ? get_value_from_opt($branch_option, $argv) : "master";
$from = in_array($from_option, $argv) ? intval(get_value_from_opt($from_option, $argv)) : null;
$to = in_array($to_option, $argv) ? intval(get_value_from_opt($to_option, $argv)) : null;
if (in_array($csv_flag, $argv)) {
    $from_extra = !is_null($from) ? "_" . $from : '';
    $to_extra = !is_null($to) ? "_" . $to : '';
    if ($from_extra === $to_extra) {
        $csv_file = $tmp_dir . string_to_legal_string($branch) . $to_extra . ".csv";
        $urls_file = $tmp_dir . "urls_" . string_to_legal_string($branch) . $to_extra . ".csv";
    } else {
        $csv_file = $tmp_dir . string_to_legal_string($branch) . $from_extra . $to_extra . ".csv";
        $urls_file = $tmp_dir . "urls_" . string_to_legal_string($branch) . $from_extra . $to_extra . ".csv";
    }
    $csv = true;
}
$show_all = in_array($show_all_flag, $argv);

if ((!is_null($from) && !is_int($from)) || (!is_null($to) && !is_int($to))) {
    echo "Error: the options --from and --to must have integer values.\n";
    echo "These values should correspond to existing builds in Bamboo\n";
    print_help();
    exit(1);
} elseif (!is_null($from) && !is_null($to) && ($to < $from)) {
    echo "Error, the value of --from must be less than or equal to the value of --to\n";
    print_help();
    exit(1);
}

// If --user, download XML files.
if (!is_null($user)) {
    download_test_results($user, $branch, $from, $to);
}

// Analyze the XML files.
parse_behat_xml($branch, $from, $to);
