<?php
declare (strict_types = 1);

namespace Translator\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Translator\Utility\Translation;

/**
 * Translate command.
 */
class TranslateCommand extends Command
{
    /**
     * Hook method for defining this command's option parser.
     *
     * @see https://book.cakephp.org/4/en/console-commands/commands.html#defining-arguments-and-options
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    public function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return null|void|int The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $this->io         = &$io;
        $language         = $args->getArgumentAt(0) ?? 'de_DE';
        $allTranslations  = file_get_contents(RESOURCES . 'locales/default.pot');
        $currTranslations = file_get_contents(RESOURCES . 'locales' . DS . $language . DS . 'default.po');

        $allTranslations  = Translation::fromPoToArray($allTranslations);
        $currTranslations = Translation::fromPoToArray($currTranslations);

        $unsetTranslations = [];
        foreach ($allTranslations as &$translation) {
            $key = $translation->key();
            if (!isset($currTranslations[$key]) && !empty($allTranslations[$key])) {
                $translation->getFilled($allTranslations[$key]);
                $unsetTranslations[$key] = $translation;
                continue;
            }
            $translation->getFilled($currTranslations[$key]);
            $currTranslations[$key] = $translation;
        }

        ksort($currTranslations);
        $fileId = uniqid('default_');
        $i      = 0;
        foreach (($currTranslations + $unsetTranslations) as $translation) {
            $filePath = RESOURCES . 'locales' . DS . $language . DS . $fileId . '.po';
            file_put_contents($filePath, (string)$translation, FILE_APPEND);
            $i++;
        }
        $io->out(__('{1} translations written into: {0}', $filePath, $i));
    }

    private function __poToArray(string $fileContent): array
    {
        $content      = explode("\n", $fileContent);
        $translation  = null;
        $lines        = [];
        $translations = [];
        foreach ($content as $i => $line) {
            if (empty(trim($line))) {
                $translation = new Translation();
                $translation->feed($lines);
                //prevent empty translations
                if ($translation->msgid()) {
                    $translations[$translation->msgid()] = $translation;
                    $lines                               = [];
                }
            } else {
                $lines[] = $line;
            }
        }
        return $translations;
    }
}
