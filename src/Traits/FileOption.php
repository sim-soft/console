<?php

namespace Simsoft\Console\Traits;

use InvalidArgumentException;
use Symfony\Component\Console\Input\InputOption;

/**
 * Trait FileOption
 *
 * @method static addOption(string $name, string|array<int, string>|null $shortcut = null, ?int $mode = null, string $description = '', mixed $default = null, array<int|string, string>|\Closure $suggestedValues = [])
 * @method mixed option(string $name, mixed $default = null)
 */
trait FileOption
{
    /**
     * Configure optional file option.
     *
     * @param string $name Input name.
     * @param string $shortcut Input shortcut.
     * @param string $description Input Description.
     * @param mixed|null $default Default value.
     * @return void
     */
    protected function addFileOption(
        string $name = 'file',
        string $shortcut = 'f',
        string $description = 'File paths. Format:filename1.xlsx,filename2.xlsx,...',
        mixed $default = null,
    ): void
    {
        $this
            ->addOption(
                $name,
                $shortcut,
                InputOption::VALUE_REQUIRED,
                $description,
                $default,
            );
    }

    /**
     * Get optional file input value.
     *
     * @param string $name Input name.
     * @param string|string[]|null $default Default value.
     * @param bool $multiple Handle multiple file names. Default: false.
     * @param string|null $fileExtension File extension.
     * @return string|array|null
     */
    protected function fileOption(
        string $name = 'file',
        string|array|null $default = null,
        bool $multiple = false,
        ?string $fileExtension = null,
    ): string|array|null
    {
        $file = $this->option($name, $default);

        if ($file === null) {
            return null;
        }

        // The $default parameter is documented as string|string[], so an array
        // can arrive here whenever the option was not supplied on the command
        // line. It went straight into trim() and raised a TypeError from inside
        // the trait, which read as a bug in this package rather than in the
        // default the caller had passed. Joining it lets the one code path
        // below handle both.
        if (is_array($file)) {
            $file = implode(',', array_map(strval(...), $file));
        }

        if (!is_string($file)) {
            throw new InvalidArgumentException(sprintf(
                'The --%s option must be a string, got %s.',
                $name,
                get_debug_type($file)
            ));
        }

        $file = trim($file, '\'"');
        $suffix = $fileExtension === null || $fileExtension === '' ? '' : ".$fileExtension";

        if ($multiple) {
            $files = [];

            foreach (explode(',', $file) as $entry) {
                $entry = trim($entry);

                // Skip empty entries rather than turning them into a filename.
                // The extension was appended unconditionally, so '--file=a,,b'
                // yielded a phantom '.xlsx' between the two real files, and
                // '--file=' yielded a list containing a single '.xlsx'. The
                // array_filter() that followed only dropped entries still empty
                // afterwards, which the suffix guaranteed they were not — so
                // the caller went looking for a file nobody had named.
                if ($entry === '') {
                    continue;
                }

                $files[] = $entry . $suffix;
            }

            return $files;
        }

        $file = trim($file);

        // Same reasoning in the single-file case: an empty value is no file,
        // not a file called '.xlsx'.
        return $file === '' ? null : $file . $suffix;
    }
}
