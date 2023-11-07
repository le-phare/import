<?php

namespace LePhare\Import;

class ImportEvents
{
    public const POST_INIT = 'import.post_init';
    public const PRE_EXECUTE = 'import.pre_execute';
    public const PRE_LOAD = 'import.pre_load';
    public const VALIDATE_SOURCE = 'import.validate_source';
    public const POST_LOAD = 'import.post_load';
    public const PRE_COPY = 'import.pre_copy';
    public const COPY = 'import.copy';
    public const POST_COPY = 'import.post_copy';
    public const POST_EXECUTE = 'import.post_execute';
    public const EXCEPTION = 'import.exception';
}
