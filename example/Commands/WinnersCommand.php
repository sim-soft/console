<?php

namespace Example\Commands;

use Simsoft\Console\Command;

class WinnersCommand extends Command
{
    static string $name = 'example:winners';
    static string $description = 'Who is the winner?';

    /**
     * @throws \Throwable
     */
    protected function handle(): void
    {
        $this->table(
            ['Place', 'Name', 'Score'],
            [
                [1, 'Zang', '100'],
                [2, 'Jane', '98'],
                [3, 'Alvin', '95'],
                [4, 'Mary', '89'],
                [5, 'Alex', '88'],
                [6, 'Wong', '87'],
            ]
        );

        $this->table(
            ['Place', 'Name', 'Score'],
            $this->getCandidates(20),
            function($obj) {
                return [$obj->position, $obj->name, $obj->score];
            }
        );
    }

    /**
     * @param int $max
     * @return \Iterator
     */
    private function getCandidates(int $max = 10): \Iterator
    {
        for($i = 1; $i <= $max; ++$i) {
            $object = new \stdClass();
            $object->position = $i;
            $object->name = "Candidate-$i";
            $object->score = $i * 100;
            yield $object;
        }
    }
}
