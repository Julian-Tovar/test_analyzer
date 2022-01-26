<?php
// Perform the XML file analysis

function parse_behat_xml($branch = "master", $from = null, $to = null) {
    global $verbose, $csv, $csv_file;
    $dirs_to_parse = unzip_build_files($branch, $from, $to);

    if ($csv) {
        $csv_fh = fopen($csv_file, "w");
        $csv_headers = "test_composite_name, test_path, feature_name, scenario_name, execution_time, status, cause,\n";
        echo "Generating the CSV file $csv_file\n";
        fwrite($csv_fh, $csv_headers);
    }

    foreach ($dirs_to_parse as $dir) {
        $xml_files = glob($dir . "/junit_files/*.xml");
        if ($verbose) {
            echo "Tests to be parsed in branch: $branch, build: " . pathinfo($dir, PATHINFO_FILENAME) . "\n";
            foreach ($xml_files as $file) {
                echo "  " . xml_name_to_test_name($file) . "\n";
            }
        }

        foreach ($xml_files as $file) {
            $reader = new XMLReader();
            $reader->open($file);

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "testsuite") {
                    $feature = $reader->getAttribute("name");
                    while ($reader->read()) {
                        if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "testcase") {
                            $scenario = $reader->getAttribute("name");
                            $execution_time = $reader->getAttribute("time");
                            $status = $reader->getAttribute("status");
                            if ($csv) {
                                $csv_line = '';
                                $csv_line = $csv_line . "\"" . $feature . " -> " . $scenario . "\"" . ",";
                                $csv_line = $csv_line . "\"" . xml_name_to_test_name($file) . "\"" . ",";
                                $csv_line = $csv_line . "\"" . $feature . "\"" . ",";
                                $csv_line = $csv_line . "\"" . $scenario . "\"" . ",";
                                $csv_line = $csv_line . "\"" . $execution_time . "\"" . ",";
                                $csv_line = $csv_line . "\"" . $status . "\"" . ",";
                                while ($reader->read()) {
                                    if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "failure") {
                                        $whole_fail = $reader->readInnerXml();
                                        preg_match("/<!\[CDATA\[(.*\n.*\n)/", $whole_fail, $summary);
                                        $cause = str_replace("\n", " ", $summary[1]);
                                        $cause = str_replace('"', "'", $cause);
                                        $csv_line = $csv_line . "\"" . $cause . "\"" . ",";
                                        break;
                                    }
                                }
                                $csv_line = $csv_line . "\n";
                                fwrite($csv_fh, $csv_line);
                            } else {
                                if ($status === "failed") {
                                    echo "\nFailure in: " . xml_name_to_test_name($file) . "\n";
                                    echo "It took $execution_time seconds\n";
                                    echo "  Feature: " . $feature . "\n";
                                    echo "  Scenario: " . $scenario . "\n";
                                    while ($reader->read()) {
                                        if ($reader->nodeType == XMLReader::ELEMENT && $reader->name === "failure") {
                                            echo "    Cause: \n";
                                            if ($verbose) {
                                                echo $reader->readInnerXml();
                                            } else {
                                                $whole_fail = $reader->readInnerXml();
                                                preg_match("/<!\[CDATA\[(.*\n.*\n)/", $whole_fail, $summary);
                                                echo $summary[1];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if ($csv) {
        fclose($csv_fh);
    }

}

function unzip_file($input_file, $output_file = null): string {

    if (is_null($output_file)) {
        $output_file = substr($input_file, 0, -4);
    }

    if (!is_file($input_file)) {
        echo "File not found: $input_file. This may produce Undefined Behavior.\n";
        echo "Please rerun this script with the --user option, or skip this build number.\n";
        echo "(If the files do exist, please check your permissions)\n";
        exit(2);
    }

    if (!is_dir($output_file)) {
        mkdir($output_file);
    }

    if (is_dir($output_file . "/junit_files")) {
        $files = glob($output_file . "/junit_files/*.xml");
        if (count($files) > 0) {
            echo "\nNotice: There are XML files already in $output_file/junit_files.\n";
            echo "Delete them if you want to re-unzip the file $input_file.\n";
            echo "Proceeding with the current XML files in $output_file/junit_files.\n\n";
            return $output_file;
        }
    }

    echo "Unzipping file $input_file, this may take a while... ";
    $zip_name = new ZipArchive();
    $ziph = $zip_name->open($input_file);
    if ($ziph === true) {
        $zip_name->extractTo($output_file);
        $zip_name->close();
        echo "Done\n";
    } else {
        echo "Notice: This code should never be executed. Please investigate\n";
        echo __FILE__ . ":" . __LINE__ . "\n";
        debug_print_backtrace();
        exit(1);
    }

    return $output_file;
}

function unzip_build_files($branch = "master", $from = null, $to = null): array {

    $branch_dir = "tmp/" . string_to_legal_string($branch);
    $current_files = glob($branch_dir . "/*.zip");
    $build_nums = array();
    $dirs_to_parse = array();
    foreach ($current_files as $file) {
        array_push($build_nums, intval(pathinfo($file, PATHINFO_FILENAME)));
    }

    if (is_null($from) && is_null($to)) {
        $max_num = max($build_nums);
        $min_num = $max_num;
    } elseif (!is_null($from) xor !is_null($to)) {
        if (!is_null($from)) {
            $max_num = max($build_nums);
            $min_num = $from;
        } else {
            $max_num = $to;
            $min_num = min($build_nums);
        }
    } else {
        $min_num = $from;
        $max_num = $to;
    }
    for ($idx = $min_num; $idx <= $max_num; $idx++) {
        $dir_to_parse = unzip_file($branch_dir . "/" . strval($idx) . ".zip");
        array_push($dirs_to_parse, $dir_to_parse);
    }

    return $dirs_to_parse;
}

function xml_name_to_test_name($xml_name): string {

    $path = pathinfo($xml_name, PATHINFO_FILENAME);
    preg_match("/^([\w_]+)(_tests_behat_)([\w_]+)(_feature_\d+)$/", $path, $parts);

    $dir = $parts[1];
    if (substr($dir, 0, 7) === "blocks_") {
        $dir = preg_replace("/_/", "/", $dir, 1);
    } else {
        $dir = preg_replace("/_/", "/", $dir);
    }
    $behat_dir = preg_replace("/_/", "/", $parts[2]);
    $test_file_name = $parts[3];
    $test_suffix = preg_replace("/_feature_(\d+)/", ".feature:$1", $parts[4]);

    return $dir . $behat_dir . $test_file_name . $test_suffix;
}
