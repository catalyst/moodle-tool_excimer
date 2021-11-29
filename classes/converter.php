<?php

namespace tool_excimer;

defined('MOODLE_INTERNAL') || die();

/**
 * Converts flamegraph data from the format given by ExcimerLog to the format required by D3.
 */
class converter {

    static function process(string $data, string $rootname = 'root'): array {
        $table = [];
        $lines = explode("\n", $data);
        $total = 0;
        foreach ($lines as $line) {
            list($trace, $num) = explode(" ", $line);
            $num = (int)$num;
            $trace = explode(';', $trace);
            $total += $num;
            self::processtail($table, $trace, $num);
        }
        return [
            'name' => $rootname,
            'value' => $total,
            'children' => self::reprocess($table)
        ];
    }

    /**
     * Converts the 'tail' of a stack trace, adding the first element to the table, and recursively calling itself with the rest.
     *
     * @param array $table
     * @param array $trace
     * @param int $num
     */
    private static function processtail(array &$table, array $trace, int $num): void {
        assert(count($trace) > 0);
        $idx = array_shift($trace);
        if (isset($table[$idx])) {
            $table[$idx]['value'] += $num;
            if (count($trace)) {
                self::processtail($table[$idx]['children'], $trace, $num);
            }
        }
        else {
            $table[$idx] = [ "value" => $num, "children" => [] ];
            if (count($trace)) {
                self::processtail($table[$idx]['children'], $trace, $num);
            }
        }
    }

    /**
     * Reprocesses the result of process() to strip away string indexes and put them inside the elements.
     *
     * @param array $table
     * @return array
     */
    private static function reprocess(array $table): array {
        $nodes = [];
        foreach ($table as $key => $val) {
            $node = [
                'name' => $key,
                'value' => $val['value'],
            ];
            if (isset($val['children'])) {
                $node['children'] = self::reprocess($val['children']);
            }
            $nodes[] = $node;
        }
        return $nodes;
    }
}

