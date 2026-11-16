<?php

/**
 * The invisible protection against spam: a field only bots fill in, and a
 * minimum time between loading the page and sending (a signed time stamp)
 */
class fwSpam
{
	/** Seconds a person needs at least between loading and sending */
	public const MIN_SECONDS = 3;

	/** A signed time stamp for the form */
	public static function stamp(): string
	{
		$time = (string) time();
		return $time . '.' . self::sign($time);
	}

	private static function sign(string $time): string
	{
		return substr(hash_hmac('sha256', 'formwizard|' . $time, self::secret()), 0, 16);
	}

	private static function secret(): string
	{
		$salt = option('content.salt');
		return hash('sha256', kirby()->root('index') . '|' . (is_string($salt) ? $salt : __FILE__));
	}

	public static function isSpam(array $data): bool
	{
		// the hidden field only bots fill in
		if (trim((string) ($data['website'] ?? '')) !== '') return true;
		[$time, $signature] = array_pad(explode('.', (string) ($data['stamp'] ?? ''), 2), 2, '');
		if (!ctype_digit($time) || !hash_equals(self::sign($time), $signature)) return true;
		return time() - (int) $time < self::MIN_SECONDS;
	}
}
