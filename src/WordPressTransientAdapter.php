<?php declare( strict_types = 1 );

/**
 * Some of the input validation taken from matthiasmullie/scrapbook
 *
 * @see https://github.com/matthiasmullie/scrapbook/blob/master/src/Psr16/SimpleCache.php
 */

namespace LachlanArthur\Psr16WordPressTransients;

use Psr\SimpleCache\CacheInterface;

class WordPressTransientAdapter implements CacheInterface {

	protected $keyPrefix = '';

	public function __construct( $keyPrefix = '' ) {

		$this->keyPrefix = $keyPrefix;

	}

	/**
	 * List of invalid (or reserved) key characters.
	 *
	 * @var string
	 */
	const KEY_INVALID_CHARACTERS = '{}()/\@:';

	/**
	 * {@inheritdoc}
	 */
	public function get( $key, $default = null ) {

		$key = $this->keyPrefix . $key;

		$this->assertValidKey( $key );

		$value = \get_transient( $key );

		if ( $value === false ) $value = null;

		return $value;

	}

	/**
	 * {@inheritdoc}
	 */
	public function set( $key, $value, $ttl = null ) {

		$key = $this->keyPrefix . $key;

		$this->assertValidKey( $key );

		$seconds = $this->ttl( $ttl );

		if ( $seconds < 0 ) {
			return $this->delete( $key );
		}

		return \set_transient( $key, $value, $seconds );

	}

	/**
	 * {@inheritdoc}
	 */
	public function delete( $key ) {

		$key = $this->keyPrefix . $key;

		$this->assertValidKey( $key );

		\delete_transient( $key );

		return true;

	}

	/**
	 * {@inheritdoc}
	 */
	public function clear() {

		// Not implemented

	}

	/**
	 * {@inheritdoc}
	 */
	public function getMultiple( $keys, $default = null ) {

		if ( $keys instanceof \Traversable ) {
			$keys = \iterator_to_array( $keys );
		}

		if ( ! \is_array( $keys ) ) {
			throw new InvalidArgumentException(
				'Invalid keys: ' . \var_export( $keys, true ) . '. Keys should be an array or Traversable of strings.'
			);
		}

		$values = [];

		foreach ( $keys as $key ) {
			$values[ $key ] = $this->get( $key, $default );
		}

		return $values;

	}

	/**
	 * {@inheritdoc}
	 */
	public function setMultiple( $values, $ttl = null ) {

		$seconds = $this->ttl( $ttl );

		if ( $seconds < 0 ) {
			return $this->deleteMultiple( $values );
		}

		if ( $values instanceof \Traversable ) {

			// we also need the keys, and an array is stricter about what it can
			// have as keys than a Traversable is, so we can't use
			// iterator_to_array...

			$array = [];

			foreach ( $values as $key => $value ) {
				if ( ! \is_string( $key ) && ! \is_int( $key ) ) {
					throw new InvalidArgumentException(
						'Invalid values: ' . \var_export( $values, true ) . '. Only strings are allowed as keys.'
					);
				}
				$array[ $key ] = $value;
			}

			$values = $array;

		}

		if ( ! is_array( $values ) ) {
			throw new InvalidArgumentException(
				'Invalid values: ' . \var_export( $values, true ) . '. Values should be an array or Traversable with strings as keys.'
			);
		}

		$success = [];

		foreach ( $values as $key => $value ) {

			$success[] = $this->set( $key, $value, $seconds );

		}

		return ! in_array( false, $success, true );

	}

	/**
	 * {@inheritdoc}
	 */
	public function deleteMultiple( $keys ) {

		if ( $keys instanceof \Traversable ) {
			$keys = \iterator_to_array( $keys );
		}

		if ( ! \is_array( $keys ) ) {
			throw new InvalidArgumentException(
				'Invalid keys: ' . \var_export( $keys, true ) . '. Keys should be an array or Traversable of strings.'
			);
		}

		$success = [];

		foreach ( $keys as $key => $value ) {

			$success[] = $this->delete( $key, $value );

		}

		return true;

	}

	/**
	 * {@inheritdoc}
	 */
	public function has( $key ) {

		return $this->get( $key ) !== null;

	}

	/**
	 * Throws an exception if $key is invalid.
	 *
	 * @param string $key
	 *
	 * @throws InvalidArgumentException
	 *
	 * @link https://github.com/matthiasmullie/scrapbook/blob/master/src/Psr16/SimpleCache.php
	 */
	protected function assertValidKey( $key ) {

		if ( ! \is_string( $key ) ) {
			throw new InvalidArgumentException(
				'Invalid key: ' . \var_export( $key, true ) . '. Key should be a string.'
			);
		}

		if ( $key === '' ) {
			throw new InvalidArgumentException(
				'Invalid key. Key should not be empty.'
			);
		}

		if ( strlen( $key ) > 172 ) {
			throw new InvalidArgumentException(
				'Invalid key. Key length should not exceed 172 characters.'
			);
		}

		// valid key according to PSR-16 rules
		$invalid = \preg_quote( static::KEY_INVALID_CHARACTERS, '/' );
		if ( \preg_match( '/[' . $invalid . ']/', $key ) ) {
			throw new InvalidArgumentException(
				'Invalid key: ' . $key . '. Contains (a) character(s) reserved '.
				'for future extension: ' . static::KEY_INVALID_CHARACTERS
			);
		}
	}

	/**
	 * Accepts all TTL inputs valid in PSR-16 (null|int|DateInterval) and
	 * converts them into TTL for WordPress Transients (int).
	 *
	 * @param null|int|\DateInterval $ttl
	 *
	 * @return int
	 *
	 * @throws \TypeError
	 */
	protected function ttl( $ttl ) {

		if ( $ttl === null ) {

			// Forever
			return 0;

		} elseif ( is_int( $ttl ) ) {

			// Expire now
			if ( $ttl <= 0 ) {
				return -1;
			}

			return $ttl;

		} elseif ( $ttl instanceof \DateInterval ) {

			$now = new \DateTimeImmutable();
			$then = $now->add( $ttl );

			return $then->getTimestamp() - $now->getTimestamp();

		}

		throw new \TypeError( 'Invalid time: ' . serialize( $ttl ) . '. Must be integer or instance of DateInterval.' );

	}

}
