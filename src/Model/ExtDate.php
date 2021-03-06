<?php

declare(strict_types=1);

namespace EDTF\Model;

use EDTF\EdtfValue;
use EDTF\PackagePrivate\Carbon\CarbonFactory;
use EDTF\PackagePrivate\Carbon\DatetimeFactoryException;
use EDTF\PackagePrivate\Carbon\DatetimeFactoryInterface;
use EDTF\PackagePrivate\CoversTrait;
use RuntimeException;

class ExtDate implements EdtfValue
{
    private const MAX_POSSIBLE_MONTH = 12;

    use CoversTrait;

    private ?int $year;
    private ?int $month;
    private ?int $day;

    // level 1 props
    private Qualification $qualification;
    private UnspecifiedDigit $unspecifiedDigit;

    private DatetimeFactoryInterface $datetimeFactory;

	protected ?int $min = null;
	protected ?int $max = null;

	// TODO: why are these fields optional?
	// TODO: this is especially weird since ExtDateTime contains an ExtDate, but AFAIK only the first 3 fields make sense there
    public function __construct(?int $year = null,
                                ?int $month = null,
                                ?int $day = null,
                                ?Qualification $qualification = null,
                                ?UnspecifiedDigit  $unspecified = null
    ){
        $this->year = $year;
        $this->month = $month;
        $this->day = $day;
        $this->qualification = is_null($qualification) ? new Qualification():$qualification;
        $this->unspecifiedDigit = is_null($unspecified) ? new UnspecifiedDigit():$unspecified;

        $this->datetimeFactory = new CarbonFactory();
    }

    /**
     * @throws RuntimeException
     */
    public function getMin(): int
    {
        if (null === $this->min) {
            $this->min = $this->calculateMin();
        }

        return $this->min;
    }

    /**
     * @throws RuntimeException
     */
    public function getMax(): int
    {
        if (null === $this->max) {
            $this->max = $this->calculateMax();
        }

        return $this->max;
    }

    /**
     * @throws RuntimeException
     */
    private function calculateMin(): int
    {
		if (null === $this->year) {
			return 0; // FIXME: this is not correct
		}

        $minMonth = $this->month ?? 1;
        $minDay = $this->day ?? 1;

        try {
            $c = $this->datetimeFactory->create($this->year, $minMonth, $minDay);
            return $c->startOfDay()->getTimestamp();
        } catch (DatetimeFactoryException $e) {
            throw new RuntimeException("Can't generate minimum date.");
        }
    }

    /**
     * @throws RuntimeException
     */
    private function calculateMax(): int
    {
		if (null === $this->year) {
			return 0; // FIXME: this is not correct
		}

        try {
            $maxYear = $this->resolveMaxYear();
            $maxMonth = $this->resolveMaxMonth();
            $maxDay = $this->resolveMaxDay($maxYear, $maxMonth);

            $c = $this->datetimeFactory->create($maxYear, $maxMonth, $maxDay);
            return $c->endOfDay()->getTimestamp();
        } catch (DatetimeFactoryException $e) {
            throw new RuntimeException("Can't generate max value");
        }
    }

    private function resolveMaxYear(): int
    {
        if ($this->unspecifiedDigit->unspecified('year')) {
            $uLen = $this->unspecifiedDigit->getYear();
            $vLen = strlen((string) $this->year);

            $specifiedPart = substr((string) $this->year, 0, $vLen - $uLen);
            $maxYearStr = $specifiedPart . str_repeat("9", $uLen);

            return (int) $maxYearStr;
        }

        return $this->year;
    }

    private function resolveMaxMonth(): int
    {
        if ($this->unspecifiedDigit->unspecified('month')) {
            $uLen = $this->unspecifiedDigit->getMonth();

            if ($this->isUnspecifiedInSecondTen($uLen, $this->month) || $this->isFullyUnspecified($uLen)) {
                return self::MAX_POSSIBLE_MONTH;
            }

            return 9;
        }

        return $this->month ?? self::MAX_POSSIBLE_MONTH;
    }

    /**
     * @throws DatetimeFactoryException
     */
    private function resolveMaxDay($year, $month): int
    {

        $lastDayOfMonth = $this->maxDaysInMonth($year, $month);

        if ($this->unspecifiedDigit->unspecified('day')) {
            $uLen = $this->unspecifiedDigit->getDay();

            // If ....-..-0X
            if ($this->isUnspecifiedInFirstTen($uLen, $this->day)) {
                return 9;
            }

            // If ....-..-1X
            if ($this->isUnspecifiedInSecondTen($uLen, $this->day)) {
                return 19;
            }

            // If ....-02-2X. Check for February, because it can have 28 days
            if ($this->isUnspecifiedInThirdTen($uLen, $this->day)) {
                return $this->isFebruary($month) ? $lastDayOfMonth : 29;
            }

            // If ....-..-XX
            return $lastDayOfMonth;
        }

        return null === $this->day ? $lastDayOfMonth : $this->day;
    }

    /**
     * This function is applicable for 2-digits placeholders (month, day).
     * Means that decimal: 0 < n < 10
     *
     * 1987-0X-12, 2000-09-0X - true
     * 1902-XX-12, 2001-11-2X, 2000-01-1X - false
     *
     * @param int $uLen Unspecified decimals length (number of "X" chars in input string)
     * @param mixed $value Resolved EDTF month (day) value
     * @return bool
     */
    private function isUnspecifiedInFirstTen(int $uLen, $value): bool
    {
        return $uLen == 1 && $value == 0;
    }

    /**
     * This function is applicable for 2-digits placeholders (month, day).
     * Means that decimal: 10 <= n < 20
     *
     * 1987-1X-12, 2000-09-1X - true
     * 1902-XX-12, 2001-11-2X, 2000-01-XX - false
     *
     * @param int $uLen Unspecified decimals length (number of "X" chars in input string)
     * @param mixed $value Resolved EDTF month (day) value
     * @return bool
     */
    private function isUnspecifiedInSecondTen(int $uLen, $value): bool
    {
        return $uLen == 1 && $value == 10;
    }

    /**
     * This function is applicable for 2-digits placeholders (month, day).
     * Means that decimal: 20 <= n < 30
     *
     * 1987-2X-12, 2000-09-2X - true
     * 1902-XX-12, 2001-11-1X, 2000-01-0X - false
     *
     * @param int $uLen Unspecified decimals length (number of "X" chars in input string)
     * @param mixed $value Resolved EDTF month (day) value
     * @return bool
     */
    private function isUnspecifiedInThirdTen(int $uLen, $value): bool
    {
        return $uLen == 1 && $value == 20;
    }

    /**
     * This function is applicable for 2-digits placeholders (month, day).
     * Means that decimal placeholder is XX
     *
     * 1990-XX-25, 1996-11-XX - true
     * 2010-1X-10, 2005-11-1X - false
     *
     * @param int $uLen Unspecified decimals length (number of "X" chars in input string)
     * @return bool
     */
    private function isFullyUnspecified(int $uLen): bool
    {
        return $uLen == 2;
    }

    private function isFebruary(int $month): bool
    {
        return $month == 2;
    }

    /**
     * @throws DatetimeFactoryException
     */
    private function maxDaysInMonth(int $year, int $month): int
    {
        $c = $this->datetimeFactory->create($year, $month);

        return $c->lastOfMonth()->day;
    }

    public function uncertain(?string $part = null): bool
    {
        return $this->qualification->uncertain($part);
    }

    public function approximate(?string $part = null): bool
    {
        return $this->qualification->approximate($part);
    }

    public function unspecified(?string $part = null): bool
    {
        return $this->unspecifiedDigit->unspecified($part);
    }

    public function getQualification(): Qualification
    {
        return $this->qualification;
    }

    public function getUnspecifiedDigit(): UnspecifiedDigit
    {
        return $this->unspecifiedDigit;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function getDay(): ?int
    {
        return $this->day;
    }

    public function setDatetimeFactory(DatetimeFactoryInterface $factory): void
    {
        $this->datetimeFactory = $factory;
    }
}