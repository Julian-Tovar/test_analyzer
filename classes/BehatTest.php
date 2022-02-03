<?php

namespace bta;

class BehatTest {

    private string $feature;
    private string $scenario;
    private string $path;
    private array $execution_times;
    private array $statuses;
    private array $causes;
    public static int $total_builds;
    private int $build_count;

    public function __construct ($feature, $scenario, $path) {
        $this->feature = $feature;
        $this->scenario = $scenario;
        $this->path = $path;
        $this->execution_times = array();
        $this->statuses = array();
        $this->causes = array();
        $this->build_count = 0;
    }

    public function get_feature(): string {
        return $this->feature;
    }

    public function get_scenario(): string {
        return $this->scenario;
    }

    public function get_path(): string {
        return $this->path;
    }

    public function get_execution_times(): array {
        return $this->execution_times;
    }

    public function get_statuses(): array {
        return $this->statuses;
    }

    public function get_causes(): array {
        return $this->causes;
    }

    public function get_build_count(): int {
        return $this->build_count;
    }

    public function append_to_execution_times($execution_time) {
        array_push($this->execution_times, $execution_time);
    }

    public function append_to_statuses($status) {
        array_push($this->statuses, $status);
    }

    public function append_to_causes($cause) {
        array_push($this->causes, $cause);
    }

    public function increase_build_count() {
        $this->build_count++;
    }

    public function write_test_as_csv(): string {
        $csv_line = '';
        $csv_line = $csv_line . "\"" . $this->feature . " -> " . $this->scenario . "\"" . ",";
        $csv_line = $csv_line . "\"" . $this->path . "\"" . ",";
        $csv_line = $csv_line . "\"" . $this->feature . "\"" . ",";
        $csv_line = $csv_line . "\"" . $this->scenario . "\"" . ",";
        for ($idx = 0; $idx < self::$total_builds; $idx++) {
            $csv_line = $csv_line . "\"" . $this->execution_times[$idx] . "\"" . ",";
            $csv_line = $csv_line . "\"" . $this->statuses[$idx] . "\"" . ",";
            $csv_line = $csv_line . "\"" . $this->causes[$idx] . "\"" . ",";
        }
        return $csv_line . "\n";
    }

}
