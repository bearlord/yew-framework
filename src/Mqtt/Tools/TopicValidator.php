<?php

namespace Yew\Mqtt\Tools;

use Yew\Core\Plugins\Logger\GetLogger;

/**
 * MQTT Topic validator.
 *
 * Rules follow spec section 4.7 "Topic Names and Topic Filters".
 *
 * UTF-8 check uses a strict byte-level validator (per MQTT-1.5.3) which
 * rejects ill-formed sequences and surrogate range U+D800~U+DFFF.
 *
 * Two contexts:
 *  - Topic Name : the target of a PUBLISH, wildcards '+' / '#' are NOT allowed.
 *  - Topic Filter: the expression of a SUBSCRIBE, wildcards allowed with rules.
 */
class TopicValidator
{

    use GetLogger;

    protected int $maxLength = 128;

    public function __construct(int $maxLength = 128)
    {
        $this->setMaxLength($maxLength);
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function setMaxLength(int $maxLength): void
    {
        $this->maxLength = $maxLength;
    }


    /**
     * Validate a Topic Name (publish target).
     * Wildcards '+' and '#' MUST NOT appear.
     */
    public function validateName(string $topic): bool
    {
        if (!$this->baseCheck($topic)) {
            return false;
        }

        // Topic Name MUST NOT contain wildcards
        if (strpos($topic, '+') !== false || strpos($topic, '#') !== false) {
            return false;
        }

        return true;
    }

    /**
     * Validate a Topic Filter (subscribe expression).
     * '#' MUST be the last char and occupy its own level;
     * '+' MUST occupy an entire level.
     */
    public function validateFilter(string $topic): bool
    {
        if (!$this->baseCheck($topic)) {
            return false;
        }

        $length = strlen($topic);

        // Multi-level wildcard '#' (MQTT-4.7.1-2)
        if (($p = strpos($topic, '#')) !== false) {
            if ($p !== $length - 1) {
                return false; // '#' must be the last char
            }
            if ($length > 1 && $topic[$length - 2] !== '/') {
                return false; // '#' must occupy an entire level
            }
        }

        // Single-level wildcard '+' MUST occupy an entire level (MQTT-4.7.1-3)
        foreach (explode('/', $topic) as $level) {
            if ($level !== '+' && strpos($level, '+') !== false) {
                return false;
            }
        }

        return true;
    }

    /**
     * Common checks shared by both contexts:
     *  - length within 1..maxLength (constructor param, default 128)
     *  - no null character (U+0000)
     *  - well-formed UTF-8 (strict, framework byte-level check)
     */
    private function baseCheck(string $topic): bool
    {
        $length = strlen($topic);
        if ($length < 1 || $length > $this->getMaxLength()) {
            return false;
        }

        if (strpos($topic, "\0") !== false) {
            return false;
        }

        if (!$this->isValidUtf8($topic)) {
            return false;
        }

        return true;
    }

    /**
     * Strict UTF-8 check (per MQTT-1.5.3) using a manual byte-level validator.
     *
     * For every multibyte sequence the minimum code point (shortest form) is
     * tracked and enforced via $minUnicodeChar, so this covers:
     *  - ill-formed / truncated sequences (incomplete trailing bytes)
     *  - BUG1 (overlong encodings, e.g. 0xC0 0x80 decoding to U+0000) -> rejected
     *  - BUG2 (code points above U+10FFFF, and 5/6-byte sequences) -> rejected
     *  - surrogate range U+D800~U+DFFF -> rejected
     *
     * Note: the dedicated U+0000 test is done in baseCheck(); a well-formed
     * overlong 0xC0 0x80 would decode to NUL, so the shortest-form check above
     * is what actually closes that NUL-smuggling hole.
     */
    private function isValidUtf8(string $string): bool
    {
        $len = strlen($string);
        if ($len === 0) return true;

        $pop10s = 0;
        $unicodeChar = 0;
        $minUnicodeChar = 0; // Track the minimum valid code point for the current sequence

        for ($i = 0; $i < $len; $i++) {
            $c = ord($string[$i]);

            if ($pop10s > 0) {
                // Check if continuation bytes strictly follow the 10xxxxxx format
                if (($c & 0xC0) != 0x80) {
                    $this->warn("Invalid continuation byte in UTF-8 sequence");
                    return false;
                }

                $unicodeChar <<= 6;
                $unicodeChar |= ($c & 0x3F);
                $pop10s--;

                if ($pop10s === 0) {
                    // Security Fix: Reject overlong sequences (e.g., 0xC0 0x80 for U+0000)
                    if ($unicodeChar < $minUnicodeChar) {
                        $this->warn("Overlong UTF-8 sequence detected (security violation)");
                        return false;
                    }

                    // MQTT-1.5.3-1: UTF-16 surrogates (U+D800 ~ U+DFFF) are strictly prohibited
                    if ($unicodeChar >= 0xD800 && $unicodeChar <= 0xDFFF) {
                        $this->warn("U+D800 ~ U+DFFF (Surrogates) are not allowed in MQTT UTF-8");
                        return false;
                    }

                    // RFC 3629 / MQTT-1.5.3-1: Code points greater than U+10FFFF are invalid
                    if ($unicodeChar > 0x10FFFF) {
                        $this->warn("Unicode code point > U+10FFFF is not allowed in MQTT UTF-8");
                        return false;
                    }
                }
            } elseif (($c & 0x7F) == $c) {
                // Single-byte ASCII character (0xxxxxxx)
                $unicodeChar = 0;
            } elseif (($c & 0xFE) == 0xFC) {
                // 6-byte sequence leading byte (1111110x)
                $pop10s = 5;
                $unicodeChar = ($c & 0x01);
                $minUnicodeChar = 0x04000000; // Minimum valid code point for 6-byte sequence
            } elseif (($c & 0xFC) == 0xF8) {
                // 5-byte sequence leading byte (111110xx)
                $pop10s = 4;
                $unicodeChar = ($c & 0x03);
                $minUnicodeChar = 0x00200000; // Minimum valid code point for 5-byte sequence
            } elseif (($c & 0xF8) == 0xF0) {
                // 4-byte sequence leading byte (11110xxx)
                $pop10s = 3;
                $unicodeChar = ($c & 0x07);
                $minUnicodeChar = 0x00010000; // Minimum valid code point for 4-byte sequence
            } elseif (($c & 0xF0) == 0xE0) {
                // 3-byte sequence leading byte (1110xxxx)
                $pop10s = 2;
                $unicodeChar = ($c & 0x0F);
                $minUnicodeChar = 0x00000800; // Minimum valid code point for 3-byte sequence
            } elseif (($c & 0xE0) == 0xC0) {
                // 2-byte sequence leading byte (110xxxxx)
                $pop10s = 1;
                $unicodeChar = ($c & 0x1F);
                $minUnicodeChar = 0x00000080; // Minimum valid code point for 2-byte sequence
            } else {
                // Invalid leading byte
                $this->warn("Bad leading byte: 0x" . dechex($c));
                return false;
            }
        }

        if ($pop10s > 0) {
            $this->warn("Incomplete UTF-8 sequence at the end of string");
            return false;
        }

        return true;
    }
}
