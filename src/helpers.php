<?php
// helpers.php

function sanitizeInput($data) {
    return htmlspecialchars(stripslashes(trim($data)));
}

/**
 * Mask contact information in text to prevent off-platform transactions.
 * Detects and replaces: emails, phone numbers, @handles, URLs/links, WhatsApp variations.
 *
 * @param string $text       The text to sanitize
 * @param string $mask       The replacement string (default: '***')
 * @param bool   $isHtml     If true, also strips href attributes containing contact info
 * @return string            Sanitized text
 */
function maskContactInfo(string $text, string $mask = '***', bool $isHtml = false): string
{
    if (trim($text) === '') return $text;

    // If HTML content, strip <a> tags with external hrefs but keep inner text
    if ($isHtml) {
        // Remove <a> tags that link to external sites / emails / tel
        $text = preg_replace(
            '/<a\b[^>]*href\s*=\s*["\']?\s*(https?:\/\/|mailto:|tel:|whatsapp:)[^"\'>\s]*["\']?[^>]*>(.*?)<\/a>/iu',
            $mask,
            $text
        ) ?? $text;
    }

    // Email addresses: user@domain.tld
    $text = preg_replace(
        '/[a-zA-Z0-9._%+\-]+\s*@\s*[a-zA-Z0-9.\-]+\s*\.\s*[a-zA-Z]{2,}/u',
        $mask,
        $text
    ) ?? $text;

    // Email with obfuscation: user [at/arroba] domain [dot/ponto] com
    $text = preg_replace(
        '/[a-zA-Z0-9._%+\-]+\s*[\[\(]?\s*(?:at|arroba)\s*[\]\)]?\s*[a-zA-Z0-9.\-]+\s*[\[\(]?\s*(?:dot|ponto)\s*[\]\)]?\s*[a-zA-Z]{2,}/iu',
        $mask,
        $text
    ) ?? $text;

    // URLs: http(s)://, www., ftp://
    $text = preg_replace(
        '~(?:https?://|ftp://|www\.)[^\s<>"\']+~iu',
        $mask,
        $text
    ) ?? $text;

    // @handles (social media): @username
    $text = preg_replace(
        '/@[a-zA-Z0-9_]{2,30}\b/u',
        $mask,
        $text
    ) ?? $text;

    // Brazilian phone numbers: (XX) XXXXX-XXXX, (XX) XXXX-XXXX, +55..., 55...
    // With or without parentheses, spaces, dashes
    $text = preg_replace(
        '/(?:\+?\d{1,3}[\s\-]?)?\(?\d{2}\)?[\s.\-]?\d{4,5}[\s.\-]?\d{4}\b/',
        $mask,
        $text
    ) ?? $text;

    // Raw consecutive digits (9+ digits — likely phone numbers)
    $text = preg_replace(
        '/\b\d{9,15}\b/',
        $mask,
        $text
    ) ?? $text;

    // WhatsApp/Telegram/Signal/Zap variations
    $text = preg_replace(
        '/\b(?:whats?\s*app|wpp|whats|zap|zapzap|telegram|signal|viber|insta(?:gram)?|face(?:book)?|discord|skype|wechat)\s*[:=]?\s*\S+/iu',
        $mask,
        $text
    ) ?? $text;

    // Keyword-only mentions: "chama no whats", "meu zap"
    $text = preg_replace(
        '/\b(?:chama\s+(?:no|meu)|meu|me\s+chama\s+no|fala\s+(?:no|comigo\s+no))\s+(?:whats?\s*app|wpp|whats|zap|zapzap|telegram|signal|insta(?:gram)?|face(?:book)?|discord)\b/iu',
        $mask,
        $text
    ) ?? $text;

    return $text;
}

/**
 * Check if text contains any blocked contact information.
 * Returns true if contact info is detected.
 */
function hasContactInfo(string $text): bool
{
    $original = $text;
    $masked = maskContactInfo($text);
    return $original !== $masked;
}

function uploadAvatar($file) {
    $targetDir = __DIR__ . '/../public/assets/uploads/';
    $targetFile = $targetDir . basename($file["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    // Check if image file is a actual image or fake image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        return false;
    }

    // Check file size
    if ($file["size"] > 500000) {
        return false;
    }

    // Allow certain file formats
    if (!in_array($imageFileType, ['jpg', 'png', 'jpeg', 'gif'])) {
        return false;
    }

    // Check if $uploadOk is set to 0 by an error
    if ($uploadOk == 0) {
        return false;
    } else {
        if (move_uploaded_file($file["tmp_name"], $targetFile)) {
            return basename($file["name"]);
        } else {
            return false;
        }
    }
}
?>