## Diacritics (umlaut)

### Convert string diacritics to ascii and non "word" characters to dash but leave slashes

    Diacritics::function path(string $input): string

### strlen() with respect to diacritics

Arguments and return value as with standard `strlen()`

    Diacritics::function strlen(string $input): int

### str_pad() with respect to diacritics

Arguments and return value as with standard `str_pad()`

    Diacritics::function str_pad(string $input, int $length, string $padString = ' ', int $padType = STR_PAD_RIGHT): string
