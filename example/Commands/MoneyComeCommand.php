<?php

namespace Example\Commands;

use Simsoft\Console\Command;

class MoneyComeCommand extends Command
{
    static string $name = 'example:money:come';
    static string $description = 'How much money do you have?';

    /**
     * @throws \Throwable
     */
    protected function handle(): void
    {
        $max = 5;
        $bar = $this->createProgressBar($max);

        $bar->setBarCharacter('$');
        $bar->setProgressCharacter('>>');
        $bar->setBarWidth(50);
        $bar->setEmptyBarCharacter('_');

        $bar->start();

        for ($i = 1; $i <= $max; ++$i) {
            // do something.
            sleep(1);
            $bar->advance();
        }
        $bar->finish();


        $this->withProgressBar($this->getCandidates(5), function($candidate) {
            //$this->info($candidate->name);
            sleep(1);
        });


        $this->withProgressBar(range(1, 10), function($item) {
            //var_dumP($item);
            sleep(1);
        });
    }

    /**
     * @param int $max
     * @return \Iterator
     */
    protected function getCandidates(int $max = 10): \Iterator
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
