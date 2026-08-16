<?php

namespace App\Admin\Plugin\Exceptions;

use RuntimeException;

/** A plugin failed to declare itself. Quarantined unless `myra.extensions.strict`. */
class PluginException extends RuntimeException
{
}
