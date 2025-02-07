<?php

declare(strict_types=1);

namespace ColinHDev\CPlot\plots\flags;

use ColinHDev\CPlot\attributes\utils\AttributeParseException;

/**
 * @template TValue of mixed
 */
interface Flag {

    /**
     * Returns the ID of the attribute.
     */
    public function getID() : string;

    /**
     * Returns the value of the flag.
     * @return TValue
     */
    public function getValue() : mixed;

    /**
     * Checks if the given flag is the same as this one and if so, checks if both share the same value.
     * @param object $other
     * @return bool
     */
    public function equals(object $other) : bool;

    /**
     * Check if the given value is equal or part of the flag's value.
     * @param (TValue is array ? value-of<TValue> : TValue) $value
     */
    public function contains(mixed $value) : bool;

    /**
     * Create a new instance of the flag with the given value.
     * @param TValue $value
     * @return static
     */
    public function createInstance(mixed $value) : static;

    /**
     * Merges this flag's value with another value and return an instance holding the merged value.
     *
     * @param TValue $value
     * @return static<TValue>
     */
    public function merge(mixed $value) : object;

    /**
     * Returns an example of a string that would parse into a valid value of this instance.
     */
    public function getExample() : string;

    /**
     * Returns a string representation of the flag instance, that when passed through {@see parse()} will result in
     * an equivalent instance of the flag.
     *
     * @return string representation of the flag
     */
    public function toString() : string;

    /**
     * Returns a more easily readable string representation of the flag instance, that might not be parseable with
     * {@see parse()}.
     * This method is used for display purposes and should not be used for storage or parsing.
     *
     * @return string representation of the flag
     */
    public function toReadableString() : string;

    /**
     * Parse a string into a flag value, and throw an exception in the case that the string does not represent a
     * valid value.
     * Returns the parsed value.
     *
     * @return TValue
     * @throws AttributeParseException if the value could not be parsed
     */
    public function parse(string $value) : mixed;
}