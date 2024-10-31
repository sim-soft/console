# FileOption Input
Get filename input from command line.
## Basic Setup
```php
<?php

namespace App;

use Simsoft\Console\Command;
use Simsoft\Console\Traits\FileOption;

class ImportFileCommand extends Command
{
    use FileOption;

    static string $name = 'import:file';
    static string $description = 'Import from files';

    protected function init(): void
    {
        /** ..... */

        $this->addFileOption('file-1');
        $this->addFileOption('file-2');
        $this->addFileOption('file-3');
    }

    protected function handle(): void
    {
        $filename = $this->fileOption('file-1'); // Get input as single filename.
        echo $filename;

        $files = $this->fileOption('file-2', multiple: false); // handle input string as multiple filename separated by commas. default behavior.
        foreach($files as $filename) {
            echo $filename;
        }

        $filename = $this->fileOption('file-3', fileExtension: 'xlsx');
        echo $filename;
    }
}
```
## Command Examples
```shell
php console.php import:file --file-1=single-file.xlsx --file-2=fileA.xlsx,fileB.xlsx,fileC.xlsx --file-3=sample-file
# file-1 Output:
# single-file.xlsx
#
# file-2 Output:
# fileA.xlsx
# fileB.xlsx
# fileC.xlsx
#
# file-3 Output:
# sample-file.xlsx
```
