<?php

declare(strict_types=1);

namespace Translator\Utility;


/**
 * [Description Translation]
 * Only need in Translatecommand (should be refactored!!
 */
class Translation implements \Stringable //, \JsonSerializable
{
	protected array $_filePositions = [];
	protected ?string $_msgctxt       = null;
	protected string $_msgid         = '';
	protected ?string $_msgid_plural  = null;
	protected array $_msgstr        = [];

	public function feed(array $lines = []): static
	{
		foreach ($lines as $line) {
			if (substr($line, 0, 2) == '#:') {
				$this->_filePositions[] = $line;
			} elseif (substr($line, 0, 12) == 'msgid_plural') {
				$this->_msgid_plural = $line;
			} elseif (substr($line, 0, 7) == 'msgctxt') {
				$this->_msgctxt = $line;
			} elseif (substr($line, 0, 5) == 'msgid') {
				$this->_msgid = $line;
			} elseif (substr($line, 0, 6) == 'msgstr') {
				$this->_msgstr[] = $line;
			}
		}
		return $this;
	}

	public function key(): string
	{
		return $this->_msgid . $this->_msgctxt;
	}

	public function msgid(bool $plural = false): ?string
	{
		return !$plural ? $this->_msgid : $this->_msgid_plural;
	}

	public function msgidHashed(bool $plural = false): string
	{
		return static::hash(!$plural ? $this->_msgid : $this->_msgid_plural);
	}

	public function msgstr(): array
	{
		return $this->_msgstr;
	}

	public function msgctxt(): string
	{
		return $this->_msgctxt;
	}

	public function filePositions(): array
	{
		return $this->_filePositions;
	}

	public function fill(self $translation): static
	{
		$translation->_filePositions = $this->filePositions();
		$translation->_msgid_plural  = $this->msgid(true);
		$translation->_msgstr        = $this->msgstr();
		return $this;
	}

	public function getFilled(self $translation): static
	{
		$this->_filePositions = $translation->filePositions();
		$this->_msgid_plural  = $translation->msgid(true);
		$this->_msgstr        = $translation->msgstr();
		return $this;
	}

	public function isPlural(): bool
	{
		return !empty($this->_msgid_plural);
	}

	public function __toString(): string
	{
		if (empty($this->msgid())) {
			return '';
		}
		$text = implode("\n", $this->_filePositions) . "\n";
		$text .= !empty($this->_msgctxt) ? $this->_msgctxt . "\n" : '';
		$text .= $this->_msgid . "\n";
		if ($this->_msgid_plural) {
			$text .= $this->_msgid_plural . "\n";
		}
		$text .= implode("\n", $this->_msgstr) . "\n";

		return $text . "\n";
	}

	public function filterMsgid(bool $plural = false)
	{
		if ($this->isPlural() && $plural) {
			preg_match('/msgid_plural\s"(.+)"/', $this->_msgid_plural, $matches);
			return $matches[1];
		}
		preg_match('/msgid\s"(.+)"/', $this->_msgid, $matches);
		return $matches[1];
	}

	public function filterMsgstr(bool $plural = false)
	{
		if ($this->isPlural()) {
			if (!$plural) {
				preg_match('/msgstr\[0\]\s"(.+)"/', $this->_msgstr[0], $matches);
			} else {
				preg_match('/msgstr\[1\]\s"(.+)"/', $this->_msgstr[1], $matches);
			}
		} else {
			preg_match('/msgstr\s"(.+)"/', $this->_msgstr[0], $matches);
		}
		return $matches[1] ?? $this->filterMsgid();
	}

	public static function fromPoToArray(string $poFileContent)
	{
		$content      = explode("\n", $poFileContent);
		$translation  = null;
		$lines        = [];
		$translations = [];
		foreach ($content as $i => $line) {
			if (empty(trim($line))) {
				$translation = new static();
				$translation->feed($lines);
				//prevent empty translations
				if ($translation->key()) {
					$translations[$translation->key()] = $translation;
					$lines                             = [];
				}
			} elseif (!in_array(trim($line), ['msgid ""'])) {
				$lines[] = $line;
			}
		}
		return $translations;
	}

	public static function hash(string $text)
	{
		return md5($text);
	}
}
