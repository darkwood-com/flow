<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// https://gpb.moe/doc/slides/Gestion_Erreur_Toutes_Ses_Couleurs_FR.pdf
// https://PHPStan.dev/r/dd8e8f59fe

/**
 * #template T1
 * #template E1.
 */
class Wrapper
{
    /**
     * #param T1 $value
     * #param E1 $err.
     */
    public function __construct(
        public mixed $value,
        public mixed $err,
        public bool $isErr = false,
    ) {
    }

    /**
     * #template T2
     * #template E2.
     *
     * #param callable(T1): Wrapper<T2, E2> $f
     *
     * #return Wrapper<T2, E1|E2>
     */
    public function bind(callable $f): self
    {
        if ($this->isErr) {
            // #var Wrapper<T2,E1> Shut up PHPStan
            return $this;
        }

        return $f($this->value);
    }
}

enum OpenFileErrors
{
    case FileDoesNotExist;
    case AccessDenied;
    case IsDirectory;
}

class File
{
}

enum GetContentErrors
{
    case E1;
    case E2;
}

enum ParseContentErrors
{
    case E1;
    case E2;
}

class UnparsedOutput
{
}
class ParsedOutput
{
}

/** #return Wrapper<File, OpenFileErrors> */
function open_file(string $path): Wrapper
{
    return new Wrapper(new File(), OpenFileErrors::FileDoesNotExist);
}

/** #return Wrapper<UnparsedOutput, GetContentErrors> */
function get_content_file(File $f): Wrapper
{
    return new Wrapper(new UnparsedOutput(), GetContentErrors::E1);
}
/** #return Wrapper<ParsedOutput, ParseContentErrors> */
function parse_content(UnparsedOutput $f): Wrapper
{
    return new Wrapper(new ParsedOutput(), ParseContentErrors::E1);
}

$data = open_file('')
    ->bind(get_content_file(...))
    ->bind(parse_content(...))
;
$result = match ($data->isErr) {
    true => match ($data->err) {
        OpenFileErrors::FileDoesNotExist => 1,
        OpenFileErrors::AccessDenied => 2,
        OpenFileErrors::IsDirectory => 3,
        GetContentErrors::E1 => 4,
        GetContentErrors::E2 => 5,
        ParseContentErrors::E1 => 6,
        ParseContentErrors::E2 => 7,
        default => 8, // added Shut up PHPStan
    },
    false => $data->value,
};
