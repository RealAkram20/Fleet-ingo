<?php

namespace App\Support;

class Search
{
    /**
     * The LIKE escape character.
     *
     * Deliberately not a backslash. MySQL processes backslash escapes inside
     * string literals and SQLite does not, so `ESCAPE '\\'` means different
     * things to each — the pattern would work in production and silently fail
     * in tests. `!` is special to neither, so one fragment works on both.
     */
    public const ESCAPE = '!';

    /**
     * Wraps a term for a LIKE clause with its wildcards neutralised.
     *
     * Bindings already stop SQL injection here. What they do not stop is the
     * wildcards themselves: a term of "%" matches every row, and something like
     * "%_%_%_%" makes the database walk the table for a pattern that can never
     * be selective. Escaping them means a person searching for a literal
     * underscore gets what they asked for, and nobody can hand the database a
     * deliberately expensive pattern.
     */
    public static function like(string $term): string
    {
        $escaped = str_replace(
            [self::ESCAPE, '%', '_'],
            [self::ESCAPE.self::ESCAPE, self::ESCAPE.'%', self::ESCAPE.'_'],
            $term,
        );

        return '%'.$escaped.'%';
    }

    /**
     * The SQL fragment to pair with like().
     *
     * The column is always a literal written in this codebase, never anything
     * that arrived in a request.
     */
    public static function clause(string $column): string
    {
        return sprintf("%s LIKE ? ESCAPE '%s'", $column, self::ESCAPE);
    }
}
