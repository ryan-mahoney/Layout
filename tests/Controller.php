<?php
class Controller {
    public function test () {
        echo json_encode([
            'data' => [
                ['value' => 'A'],
                ['value' => 'B'],
                ['value' => 'C']
            ]
        ]);
    }
}