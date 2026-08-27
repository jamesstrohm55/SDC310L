<?php
/**
 * Tests for cross-site request forgery tokens.
 *
 * Csrf holds no state and touches no superglobal: generate() mints a token
 * and matches() compares two of them. Storage is SessionCart's job, which is
 * what lets every rule here be checked with no session and no web request.
 */

declare(strict_types=1);

// ---------------------------------------------------------------------------
describe('Csrf::generate');

$token = Csrf::generate();

assert_same(32, strlen($token), 'a token is 32 characters long');
assert_same(1, preg_match('/^[0-9a-f]{32}$/', $token), 'a token is lowercase hexadecimal');

// A predictable token is no token at all: if an attacker can guess the value,
// embedding it in a forged form costs them nothing.
$tokens = [];
for ($i = 0; $i < 50; $i++) {
    $tokens[] = Csrf::generate();
}
assert_same(50, count(array_unique($tokens)), '50 generated tokens are all distinct');

// ---------------------------------------------------------------------------
describe('Csrf::matches — accepting the real token');

$token = Csrf::generate();
assert_true(Csrf::matches($token, $token), 'a token matches itself');
assert_true(
    Csrf::matches('0123456789abcdef0123456789abcdef', '0123456789abcdef0123456789abcdef'),
    'two equal token strings match'
);

// ---------------------------------------------------------------------------
describe('Csrf::matches — rejecting anything else');

$token = '0123456789abcdef0123456789abcdef';

assert_same(false, Csrf::matches($token, 'fedcba9876543210fedcba9876543210'), 'a different token is rejected');
assert_same(false, Csrf::matches($token, strtoupper($token)), 'the comparison is case sensitive');
assert_same(false, Csrf::matches($token, substr($token, 0, 31)), 'a truncated token is rejected');
assert_same(false, Csrf::matches($token, $token . 'a'), 'an extended token is rejected');
assert_same(false, Csrf::matches($token, ' ' . $token), 'a padded token is rejected');

// The absent cases are the ones a forged form actually produces: an attacker's
// page cannot read the victim's session, so it submits no token at all.
assert_same(false, Csrf::matches($token, ''), 'an empty submitted token is rejected');
assert_same(false, Csrf::matches($token, null), 'an absent submitted token is rejected');

// If the session somehow holds no token, nothing may match it — least of all
// another empty string, which is exactly what a form with no hidden field
// submits. Failing open here would defeat the whole check.
assert_same(false, Csrf::matches('', ''), 'an empty expected token matches nothing, not even empty');
assert_same(false, Csrf::matches('', $token), 'an empty expected token rejects a real-looking token');
assert_same(false, Csrf::matches(null, null), 'two absent tokens do not match');
assert_same(false, Csrf::matches(null, $token), 'an absent expected token rejects everything');

// ---------------------------------------------------------------------------
describe('SessionCart::token');

$_SESSION = [];
$minted = SessionCart::token();

assert_same(1, preg_match('/^[0-9a-f]{32}$/', $minted), 'the first call mints a real token');
assert_same($minted, $_SESSION['csrf'], 'the minted token is stored in the session');

// The token must be stable for the life of the session. Minting a fresh one
// per request would invalidate every form already rendered in the browser,
// so the visitor's next click would be rejected as a forgery.
assert_same($minted, SessionCart::token(), 'a second call returns the same token');
assert_same($minted, SessionCart::token(), 'a third call returns the same token');

// A separate session gets a separate token, or one visitor's token would
// authorise a request forged against another.
$_SESSION = [];
assert_true(SessionCart::token() !== $minted, 'a new session mints a different token');

// A session written by an older build has no csrf key; one hand-edited to a
// non-string must not hand a non-string to hash_equals.
$_SESSION = ['csrf' => ['not', 'a', 'string']];
assert_same(1, preg_match('/^[0-9a-f]{32}$/', SessionCart::token()), 'a non-string stored token is replaced');

$_SESSION = ['csrf' => ''];
assert_same(1, preg_match('/^[0-9a-f]{32}$/', SessionCart::token()), 'an empty stored token is replaced');

$_SESSION = ['cart' => [1 => 2], 'flash' => 'keep me'];
SessionCart::token();
assert_same([1 => 2], $_SESSION['cart'], 'minting a token does not disturb the cart');
assert_same('keep me', $_SESSION['flash'], 'minting a token does not disturb the flash message');

$_SESSION = [];
