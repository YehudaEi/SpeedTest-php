<?php
/**
 * idObfuscation.php
 * ─────────────────────────────────────────────────────────────────────────────
 * Provides a fast, *reversible* transform that converts sequential database
 * auto-increment IDs into short, non-sequential public tokens (base-36 strings)
 * so that users cannot guess or enumerate other test results.
 *
 * ⚠ This is NOT cryptographic security — do not use it for passwords or
 *   anything that must be secret. It merely hides the database structure.
 *
 * How it works (3-step XOR/bit shuffle on a 32-bit integer):
 *   1. Swap the high 16 bits with the low 16 bits.
 *   2. Swap every pair of adjacent bits (odd ↔ even).
 *   3. XOR the result with a random 32-bit salt stored on disk.
 * All three steps are their own inverse, so decode = encode in reverse order.
 * ─────────────────────────────────────────────────────────────────────────────
 */


/**
 * Returns the 32-bit salt used for obfuscation.
 *
 * On first call the salt does not exist yet, so a new one is generated with
 * openssl_random_pseudo_bytes(), written to idObfuscation_salt.php inside the
 * same directory, and then returned. On every subsequent call the cached file
 * is simply require'd.
 *
 * @return int  Unsigned 32-bit integer (stored as a PHP int / hex literal).
 */
function getObfuscationSalt(): int
{
    $saltFile = __DIR__ . '/idObfuscation_salt.php';

    if (!file_exists($saltFile)) {
        // Generate 4 random bytes → one 32-bit integer.
        $randomBytes = openssl_random_pseudo_bytes(4);
        $hexSalt     = bin2hex($randomBytes);           // e.g. "ae52db55"

        // Write a tiny PHP file that just sets the variable.
        $handle = fopen($saltFile, 'w');
        fwrite($handle, "<?php\n");
        fwrite($handle, "\$OBFUSCATION_SALT = 0x{$hexSalt};\n");
        fclose($handle);
    }

    require $saltFile;   // defines $OBFUSCATION_SALT

    return isset($OBFUSCATION_SALT) ? (int)$OBFUSCATION_SALT : 0;
}


/**
 * Core encode/decode transform (shared by both directions).
 *
 * @param int  $id   The 32-bit value to transform.
 * @param bool $dec  true = decode (public token → DB id), false = encode.
 * @return int       The transformed 32-bit value.
 */
function obfTransform(int $id, bool $dec): int
{
    $salt = getObfuscationSalt() & 0xFFFFFFFF;
    $id   = $id & 0xFFFFFFFF;

    if ($dec) {
        // Decode: reverse the three encode steps.
        $id = $id ^ $salt;                                           // step 3 reversed
        $id = (($id & 0xAAAAAAAA) >> 1) | (($id & 0x55555555) << 1); // step 2 reversed
        $id = (($id & 0x0000FFFF) << 16) | (($id & 0xFFFF0000) >> 16); // step 1 reversed
    } else {
        // Encode: apply the three steps in order.
        $id = (($id & 0x0000FFFF) << 16) | (($id & 0xFFFF0000) >> 16); // step 1: swap halves
        $id = (($id & 0xAAAAAAAA) >> 1) | (($id & 0x55555555) << 1); // step 2: swap bit pairs
        $id = $id ^ $salt;                                           // step 3: XOR with salt
    }

    return $id;
}


/**
 * Converts a plain database ID into a 7-character base-36 public token.
 *
 * We add 1 before encoding so that ID 0 does not produce an all-zero token,
 * and pad to exactly 7 characters for a consistent URL length.
 *
 * @param int $id  Database auto-increment ID (≥ 0).
 * @return string  7-character base-36 string, e.g. "00a3b2f".
 */
function obfuscateId(int $id): string
{
    $transformed = obfTransform($id + 1, false);
    return str_pad(base_convert((string)$transformed, 10, 36), 7, '0', STR_PAD_LEFT);
}


/**
 * Converts a public base-36 token back to the original database ID.
 *
 * @param string $token  The 7-character base-36 token from obfuscateId().
 * @return int           The original database ID.
 */
function deobfuscateId(string $token): int
{
    $numeric = (int)base_convert($token, 36, 10);
    return obfTransform($numeric, true) - 1;
}

//IMPORTANT: DO NOT ADD ANYTHING BELOW THE PHP CLOSING TAG, NOT EVEN EMPTY LINES!
?>
