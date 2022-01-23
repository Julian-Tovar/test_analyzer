<?php
// In kow-bta.php, bta stands for Behat Test Analyzer.

require("lib.php");
require("bta.php");

// Parse command line arguments

$sn = $argv[0]; // Script name.
$args = array_slice($argv, 1);
$verbose = false;

$help_flags = ['--help', '-h', '--usage', '--man'];
if (!empty(array_intersect($help_flags, $args))) {
    print_description();
    exit(0);
}

$user_option = '--user';
$branch_option = '--branch';
$from_option = '--from';
$to_option = '--to';

$verbose_flag = '--verbose';

$other_flags = [$verbose_flag];
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

if ((!is_null($from) && !is_int($from)) || (!is_null($to) && !is_int($to))) {
    echo "Error: the options --from and --to must have integer values.\n";
    echo "These values should correspond to existing builds in Bamboo\n";
    print_help();
    exit(1);
} elseif (!is_null($from) && !is_null($to) && ($to <= $from)) {
    echo "Error, the value of --from must be strictly less than the value of --to\n";
    print_help();
    exit(1);
}

// If --user, download XML files.
if (!is_null($user)) {
    download_test_results($user, $branch, $from, $to);
}

// Analyze the XML files.
parse_behat_xml($branch, $from, $to);
