<?php

declare(strict_types=1);

namespace Doogle\Auth;

use InvalidArgumentException;

final class QrCodeSvg
{
    private const VERSION = 5;
    private const SIZE = 37;
    private const DATA_CODEWORDS = 108;
    private const ECC_CODEWORDS = 26;
    private const FORMAT_L_MASK_0 = 0x77c4;

    /**
     * @return non-empty-string
     */
    public function render(string $text, int $scale = 5): string
    {
        $modules = $this->encode($text);
        $size = self::SIZE * $scale;
        $paths = [];

        foreach ($modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $paths[] = 'M' . ($x * $scale) . ' ' . ($y * $scale)
                        . 'h' . $scale . 'v' . $scale . 'h-' . $scale . 'z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
            . '" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="TOTP QR code">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<path fill="#111827" d="' . implode('', $paths) . '"/></svg>';
    }

    /**
     * @return list<list<bool>>
     */
    private function encode(string $text): array
    {
        $data = $this->dataCodewords($text);
        $codewords = array_merge($data, $this->reedSolomonRemainder($data, self::ECC_CODEWORDS));
        $modules = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));
        $function = array_fill(0, self::SIZE, array_fill(0, self::SIZE, false));

        $this->drawFunctionPatterns($modules, $function);
        $this->drawCodewords($modules, $function, $codewords);
        $this->drawFormatBits($modules, $function);

        return $modules;
    }

    /**
     * @return list<int>
     */
    private function dataCodewords(string $text): array
    {
        $bytes = array_values(unpack('C*', $text) ?: []);

        if (count($bytes) > 106) {
            throw new InvalidArgumentException('QR code text is too long for the bundled renderer.');
        }

        $bits = '0100' . str_pad(decbin(count($bytes)), 8, '0', STR_PAD_LEFT);

        foreach ($bytes as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = self::DATA_CODEWORDS * 8;
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        $bits .= str_repeat('0', (8 - (strlen($bits) % 8)) % 8);

        $codewords = [];

        foreach (str_split($bits, 8) as $chunk) {
            $codewords[] = bindec($chunk);
        }

        for ($pad = 0xec; count($codewords) < self::DATA_CODEWORDS; $pad = $pad === 0xec ? 0x11 : 0xec) {
            $codewords[] = $pad;
        }

        return $codewords;
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private function drawFunctionPatterns(array &$modules, array &$function): void
    {
        $this->drawFinder($modules, $function, 3, 3);
        $this->drawFinder($modules, $function, self::SIZE - 4, 3);
        $this->drawFinder($modules, $function, 3, self::SIZE - 4);
        $this->drawAlignment($modules, $function, 30, 30);

        for ($i = 0; $i < self::SIZE; $i++) {
            if (!$function[6][$i]) {
                $this->setFunction($modules, $function, $i, 6, $i % 2 === 0);
            }

            if (!$function[$i][6]) {
                $this->setFunction($modules, $function, 6, $i, $i % 2 === 0);
            }
        }

        $this->setFunction($modules, $function, 8, (4 * self::VERSION) + 9, true);
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private function drawFinder(array &$modules, array &$function, int $centerX, int $centerY): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $centerX + $dx;
                $y = $centerY + $dy;

                if ($x < 0 || $x >= self::SIZE || $y < 0 || $y >= self::SIZE) {
                    continue;
                }

                $distance = max(abs($dx), abs($dy));
                $this->setFunction($modules, $function, $x, $y, $distance !== 2 && $distance !== 4);
            }
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private function drawAlignment(array &$modules, array &$function, int $centerX, int $centerY): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $distance = max(abs($dx), abs($dy));
                $this->setFunction($modules, $function, $centerX + $dx, $centerY + $dy, $distance !== 1);
            }
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     * @param list<int> $codewords
     */
    private function drawCodewords(array &$modules, array $function, array $codewords): void
    {
        $bits = '';

        foreach ($codewords as $codeword) {
            $bits .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }

        $bitIndex = 0;
        $upward = true;

        for ($right = self::SIZE - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right--;
            }

            for ($vertical = 0; $vertical < self::SIZE; $vertical++) {
                $y = $upward ? self::SIZE - 1 - $vertical : $vertical;

                for ($column = 0; $column < 2; $column++) {
                    $x = $right - $column;

                    if ($function[$y][$x]) {
                        continue;
                    }

                    $dark = $bitIndex < strlen($bits) && $bits[$bitIndex] === '1';
                    $bitIndex++;

                    if (($x + $y) % 2 === 0) {
                        $dark = !$dark;
                    }

                    $modules[$y][$x] = $dark;
                }
            }

            $upward = !$upward;
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private function drawFormatBits(array &$modules, array &$function): void
    {
        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction($modules, $function, 8, $i, ((self::FORMAT_L_MASK_0 >> $i) & 1) !== 0);
        }

        $this->setFunction($modules, $function, 8, 7, ((self::FORMAT_L_MASK_0 >> 6) & 1) !== 0);
        $this->setFunction($modules, $function, 8, 8, ((self::FORMAT_L_MASK_0 >> 7) & 1) !== 0);
        $this->setFunction($modules, $function, 7, 8, ((self::FORMAT_L_MASK_0 >> 8) & 1) !== 0);

        for ($i = 9; $i < 15; $i++) {
            $this->setFunction($modules, $function, 14 - $i, 8, ((self::FORMAT_L_MASK_0 >> $i) & 1) !== 0);
        }

        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($modules, $function, self::SIZE - 1 - $i, 8, ((self::FORMAT_L_MASK_0 >> $i) & 1) !== 0);
        }

        for ($i = 8; $i < 15; $i++) {
            $this->setFunction($modules, $function, 8, self::SIZE - 15 + $i, ((self::FORMAT_L_MASK_0 >> $i) & 1) !== 0);
        }
    }

    /**
     * @param list<list<bool>> $modules
     * @param list<list<bool>> $function
     */
    private function setFunction(array &$modules, array &$function, int $x, int $y, bool $dark): void
    {
        $modules[$y][$x] = $dark;
        $function[$y][$x] = true;
    }

    /**
     * @param list<int> $data
     *
     * @return list<int>
     */
    private function reedSolomonRemainder(array $data, int $degree): array
    {
        $generator = $this->reedSolomonGenerator($degree);
        $result = array_fill(0, $degree, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;

            foreach ($generator as $i => $coefficient) {
                $result[$i] ^= $this->fieldMultiply($coefficient, $factor);
            }
        }

        return $result;
    }

    /**
     * @return list<int>
     */
    private function reedSolomonGenerator(int $degree): array
    {
        $result = [1];

        for ($i = 0; $i < $degree; $i++) {
            $result[] = 0;

            for ($j = count($result) - 1; $j > 0; $j--) {
                $result[$j] = $result[$j - 1] ^ $this->fieldMultiply($result[$j], $this->fieldPower(2, $i));
            }

            $result[0] = $this->fieldMultiply($result[0], $this->fieldPower(2, $i));
        }

        return array_slice($result, 0, $degree);
    }

    private function fieldPower(int $value, int $power): int
    {
        $result = 1;

        for ($i = 0; $i < $power; $i++) {
            $result = $this->fieldMultiply($result, $value);
        }

        return $result;
    }

    private function fieldMultiply(int $left, int $right): int
    {
        $result = 0;

        for ($i = 7; $i >= 0; $i--) {
            $result = (($result << 1) ^ (($result >> 7) * 0x11d)) & 0xff;

            if ((($right >> $i) & 1) !== 0) {
                $result ^= $left;
            }
        }

        return $result;
    }
}
