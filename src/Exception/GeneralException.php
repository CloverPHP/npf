<?php

namespace Npf\Exception;

use Npf\Core\Exception;

class GeneralException extends Exception
{
    /**
     * @var string
     */
    protected string $error = 'general_exception';

    /**
     * @var bool
     */
    protected bool $sysLog = true;

    final public function __construct(\Throwable $exception)
    {
        $this->error = $this->updateCode(get_class($exception));
        self::$previous = $exception;
        parent::__construct($exception->getMessage(), $exception->getCode());
    }

    private function updateCode(string $code): string
    {
        $result = '';
        $codes = str_split($code);
        foreach ($codes as $key => $chr)
            $result .= ctype_upper($chr) ? (($key > 0 ? "_" : "") . strtolower($chr)) : $chr;
        return $result;
    }
}