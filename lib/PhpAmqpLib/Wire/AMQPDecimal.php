<?php
/**
 * AMQP protocol decimal value.
 *
 * Values are represented as (n,e) pairs. The actual value
 * is n * 10^(-e).
 *
 * From 0.8 spec: Decimal values are
 * not intended to support floating point values, but rather
 * business values such as currency rates and amounts. The
 * 'decimals' octet is not signed.
 */
class PhpAmqpLib_Wire_AMQPDecimal
{
    public function __construct($n, $e)
    {
        if($e < 0) {
            throw new PhpAmqpLib_Exception_AMQPOutOfBoundsException("Decimal exponent value must be unsigned!");
        }

        $this->n = $n;
        $this->e = $e;
    }

    public function asBCvalue()
    {
        return bcdiv($this->n, bcpow(10,$this->e));
    }
}
