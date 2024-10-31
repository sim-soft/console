# DateRangeOption Input
Get date range input from command line.
## Basic Setup
```php
<?php

namespace App;

use Simsoft\Console\Command;
use Simsoft\Console\Traits\DateRangeOption;

class UpdateStatusCommand extends Command
{
    use DateRangeOption;

    static string $name = 'update:status';
    static string $description = 'Update record status within a given date range.';

    protected function init(): void
    {
        /** ..... */

        $this->addDateRangeOption();
    }

    protected function handle(): void
    {
        [$fromDate, $toDate] = $this->dateRangeOption();

        /** Immutable Datetime object. */
        echo $fromDate?->format('Y-m-d');
        echo $toDate?->format('Y-m-d');

    }
}
```

## Command Examples
```shell
php console.php update:status --from-date=2024-01-01 --to-date=2024-03-31
# Output:
# 2024-01-01
# 2024-03-31
```

```shell
php console.php update:status --for-month=2024-01
# Output:
# 2024-01-01
# 2024-01-31
```
