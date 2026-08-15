<?php

namespace App\Admin\Search;

use RuntimeException;

/**
 * Forgetting an ownership scope must be a boot error, not a silent leak.
 */
class MissingSearchScopeException extends RuntimeException {}
