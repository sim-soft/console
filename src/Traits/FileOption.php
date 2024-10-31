<?php

namespace Simsoft\Console\Traits;

use Symfony\Component\Console\Input\InputOption;

/**
 * Trait FileOption
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

        $file = trim($file, '\'"');
        $fileExtension = $fileExtension ? ".$fileExtension" : null;

        if ($multiple) {
            $files = [];
            foreach (explode(',', $file) as $file) {
                $files[] = trim($file).$fileExtension;
            }
            return array_filter($files);
        }

        return trim($file).$fileExtension;
    }
}
