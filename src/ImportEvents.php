<?php

namespace LePhare\Import;

class ImportEvents
{
    const POST_INIT       = 'import.post_init';
    const PRE_EXECUTE     = 'import.pre_execute';
    const PRE_LOAD        = 'import.pre_load';
    const VALIDATE_SOURCE = 'import.validate_source';
    const POST_LOAD       = 'import.post_load';
    const PRE_COPY        = 'import.pre_copy';
    const COPY            = 'import.copy';
    const POST_COPY       = 'import.post_copy';
    const POST_EXECUTE    = 'import.post_execute';
    const EXCEPTION       = 'import.exception';
}
