<?php

namespace App\Services;

use App\Core\Database;
use PDO;

class PixService
{
    /**
     * Remove acentos e caracteres especiais para conformidade com a norma EMV / BCB
     */
    public static function removeAccents(string $string): string
    {
        $transliterator = 'Any-Latin; Latin-ASCII; [\u0080-\u7fff] remove';
        if (function_exists('transliterator_transliterate')) {
            $clean = transliterator_transliterate($transliterator, $string) ?: $string;
        } else {
            $clean = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $string) ?: $string;
        }
        return preg_replace('/[^a-zA-Z0-9 \.\-_@]/', '', $clean);
    }

    /**
     * Formata um campo no padrão EMVCo TLV (Tag, Length, Value)
     */
    public static function emv(string $id, string $value): string
    {
        $len = strlen($value);
        return sprintf('%02s%02d%s', $id, $len, $value);
    }

    /**
     * Calcula o CRC16 CCITT (0xFFFF) no padrão do Banco Central do Brasil
     */
    public static function crc16(string $payload): string
    {
        $payload .= '6304';
        $polynomial = 0x1021;
        $crc = 0xFFFF;

        for ($i = 0; $i < strlen($payload); $i++) {
            $crc ^= (ord($payload[$i]) << 8);
            for ($j = 0; $j < 8; $j++) {
                if (($crc & 0x8000) !== 0) {
                    $crc = (($crc << 1) ^ $polynomial) & 0xFFFF;
                } else {
                    $crc = ($crc << 1) & 0xFFFF;
                }
            }
        }

        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Normaliza obrigatoriamente a chave PIX de acordo com o padrão Bacen / DICT
     *
     * Para CNPJ:
     * - remover ".", "/", "-", espaços e outros separadores;
     * - preservar letras caso seja CNPJ alfanumérico;
     * - converter letras para maiúsculas;
     * - resultado deve possuir 14 caracteres alfanuméricos;
     * - nunca enviar a versão mascarada ao payload.
     *
     * Para CPF:
     * - somente 11 dígitos.
     *
     * Para telefone:
     * - utilizar formato internacional DICT, incluindo +55.
     *
     * Para e-mail:
     * - utilizar chave conforme registrada no DICT, removendo apenas espaços indevidos no início/fim.
     *
     * Para chave aleatória:
     * - preservar UUID/pontuação conforme registrado.
     */
    public static function normalizePixKey(string $key, ?string $type = null): string
    {
        $key = trim($key);
        if (empty($key)) {
            return '';
        }

        $type = strtoupper(trim((string)$type));

        // 1. E-mail (se contém @ ou tipo selecionado é EMAIL)
        if (str_contains($key, '@') || $type === 'EMAIL') {
            return trim($key);
        }

        // 2. Chave Aleatória (EVP / UUID v4)
        if ($type === 'CHAVE_ALEATORIA' || $type === 'ALEATORIA' || preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $key)) {
            return trim($key);
        }

        // 3. CNPJ (14 caracteres alfanuméricos conforme padrão Bacen/Receita)
        $alnumClean = strtoupper((string)preg_replace('/[^a-zA-Z0-9]/', '', $key));
        if ($type === 'CNPJ' || (strlen($alnumClean) === 14 && $type !== 'CPF' && $type !== 'TELEFONE')) {
            return $alnumClean;
        }

        // 4. CPF (11 dígitos numéricos)
        $digits = (string)preg_replace('/\D/', '', $key);
        if ($type === 'CPF' || (strlen($digits) === 11 && $type !== 'TELEFONE' && $type !== 'CELULAR')) {
            return $digits;
        }

        // 5. Celular / Telefone (formato internacional DICT +55...)
        if ($type === 'TELEFONE' || $type === 'PHONE' || $type === 'CELULAR' || (strlen($digits) >= 10 && strlen($digits) <= 13)) {
            if (str_starts_with($key, '+')) {
                return '+' . $digits;
            }
            if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
                return '+' . $digits;
            }
            if (strlen($digits) >= 10 && strlen($digits) <= 11) {
                return '+55' . $digits;
            }
            return '+' . $digits;
        }

        return $alnumClean ?: $key;
    }

    /**
     * Alias de retrocompatibilidade para normalizePixKey
     */
    public static function normalizeKey(string $key, ?string $type = null): string
    {
        return self::normalizePixKey($key, $type);
    }

    /**
     * Validação semântica específica Pix antes da geração do payload
     */
    public static function validateSemantic(
        string $pixKey,
        ?string $pixKeyType,
        string $receiverName,
        string $receiverCity,
        ?string $description = null,
        string $txid = '***'
    ): array {
        $errors = [];
        $type = strtoupper(trim((string)$pixKeyType));
        $normalizedKey = self::normalizePixKey($pixKey, $type);

        if (empty($normalizedKey)) {
            $errors[] = 'Chave Pix não pode estar vazia.';
        } else {
            switch ($type) {
                case 'CNPJ':
                    if (strlen($normalizedKey) !== 14 || !preg_match('/^[0-9A-Z]{14}$/', $normalizedKey)) {
                        $errors[] = 'Chave CNPJ deve conter exatamente 14 caracteres alfanuméricos normalizados.';
                    }
                    break;
                case 'CPF':
                    if (strlen($normalizedKey) !== 11 || !ctype_digit($normalizedKey)) {
                        $errors[] = 'Chave CPF deve conter exatamente 11 dígitos numéricos.';
                    }
                    break;
                case 'TELEFONE':
                case 'PHONE':
                case 'CELULAR':
                    if (!preg_match('/^\+[1-9][0-9]{10,14}$/', $normalizedKey)) {
                        $errors[] = 'Chave de telefone deve estar no formato internacional (+55...).';
                    }
                    break;
                case 'EMAIL':
                    if (!filter_var($normalizedKey, FILTER_VALIDATE_EMAIL)) {
                        $errors[] = 'Chave de e-mail com formato inválido.';
                    }
                    break;
                case 'CHAVE_ALEATORIA':
                case 'ALEATORIA':
                    if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $normalizedKey)) {
                        $errors[] = 'Chave aleatória (EVP) deve seguir o formato UUID.';
                    }
                    break;
            }
        }

        $cleanName = trim(self::removeAccents($receiverName));
        if (empty($cleanName)) {
            $errors[] = 'Nome do recebedor é obrigatório.';
        }

        $cleanCity = trim(self::removeAccents($receiverCity));
        if (empty($cleanCity)) {
            $errors[] = 'Cidade é obrigatória.';
        }

        if (!empty($description) && strlen(trim(self::removeAccents($description))) > 50) {
            $errors[] = 'A observação/descrição não pode exceder 50 caracteres.';
        }

        return [
            'valid' => empty($errors),
            'message' => empty($errors) ? 'ESTRUTURA BR CODE VÁLIDA' : implode(' | ', $errors),
            'errors' => $errors,
            'normalized_key' => $normalizedKey,
        ];
    }

    /**
     * Gera o Payload oficial do Pix no padrão do Banco Central do Brasil (BR Code / EMVCo)
     * Compatível 100% com Banco do Brasil, Itaú, Nubank, Bradesco, Santander, Caixa, Inter, Mercado Pago, Sicoob, Sicredi.
     */
    public static function createPayload(
        string $pixKey,
        string $receiverName,
        string $receiverCity,
        ?string $description = null,
        ?float $amount = null,
        string $txid = '***',
        ?string $keyType = null
    ): string {
        // Normalização mandatória da chave Pix antes da montagem dos blocos TLV
        $cleanPixKey = self::normalizePixKey($pixKey, $keyType);
        if (empty($cleanPixKey)) {
            return '';
        }

        // 1. Merchant Account Information (Tag 26)
        // Subcampo 00: GUI fixa br.gov.bcb.pix
        // Subcampo 01: Chave Pix DICT normalizada (sem pontuação/máscaras de CPF/CNPJ)
        // Subcampo 02: infoAdicional / Descrição (opcional, máx. 50 caracteres)
        $accountInfo = self::emv('00', 'br.gov.bcb.pix') . self::emv('01', $cleanPixKey);

        $cleanDesc = trim(self::removeAccents($description ?? ''));
        if ($cleanDesc !== '') {
            $cleanDesc = substr($cleanDesc, 0, 50);
            $accountInfo .= self::emv('02', $cleanDesc);
        }

        // Sanitizar nome do recebedor (Tag 59 - Max 25 caracteres, sem acentos, letras maiúsculas, padrão BCB)
        $cleanName = strtoupper(substr(trim(self::removeAccents($receiverName ?: 'Show de Premios')), 0, 25));
        if (empty($cleanName)) {
            $cleanName = 'SHOW DE PREMIOS';
        }

        // Sanitizar cidade (Tag 60 - Max 15 caracteres, sem acentos, letras maiúsculas, SEM concatenação de UF)
        // Ex: Ubatuba -> UBATUBA (nunca UBATUBASP)
        $cleanCity = strtoupper(substr(trim(self::removeAccents($receiverCity ?: 'SAO PAULO')), 0, 15));
        if (empty($cleanCity)) {
            $cleanCity = 'SAO PAULO';
        }

        // Montagem dos campos TLV em conformidade estrita com o padrão EMVCo / Bacen
        $payload = '';
        $payload .= self::emv('00', '01');                // 00: Payload Format Indicator
        $payload .= self::emv('26', $accountInfo);         // 26: Merchant Account Information
        $payload .= self::emv('52', '0000');               // 52: Merchant Category Code (0000 padrão geral)
        $payload .= self::emv('53', '986');                // 53: Transaction Currency (986 = Real Brasileiro)

        if ($amount !== null && $amount > 0) {
            $payload .= self::emv('54', number_format($amount, 2, '.', ''));
        }

        $payload .= self::emv('58', 'BR');                 // 58: Country Code (BR)
        $payload .= self::emv('59', $cleanName);            // 59: Merchant Name (máx. 25)
        $payload .= self::emv('60', $cleanCity);            // 60: Merchant City (máx. 15, sem UF)

        // Campo adicional (Tag 62) - TXID / Referência (subcampo 05 com letras/números ou ***)
        $cleanTxid = preg_replace('/[^a-zA-Z0-9]/', '', $txid ?: '');
        if (empty($cleanTxid)) {
            $cleanTxid = '***';
        } else {
            $cleanTxid = substr($cleanTxid, 0, 25);
        }
        $additionalData = self::emv('05', $cleanTxid);
        $payload .= self::emv('62', $additionalData);

        // Adiciona Tag 63 e calcula CRC16 final sobre a string completa + "6304"
        $crc = self::crc16($payload);
        return $payload . '6304' . $crc;
    }

    /**
     * Retorna os dados completos do PIX configurados no sistema
     */
    public static function getConfig(): array
    {
        $pdo = Database::getConnection();
        $stmt = $pdo->query("SELECT key, value FROM settings WHERE key LIKE 'pix_%'");
        $settings = $stmt ? $stmt->fetchAll(PDO::FETCH_KEY_PAIR) : [];

        $rawKey = trim($settings['pix_key'] ?? '');
        $key = !empty($rawKey) ? $rawKey : 'mskpoeira@gmail.com';
        $type = trim($settings['pix_key_type'] ?? 'EMAIL');
        $receiver = trim($settings['pix_receiver_name'] ?? '') ?: 'Show de Premios';
        $city = trim($settings['pix_receiver_city'] ?? '') ?: 'SAO PAULO';
        $desc = trim($settings['pix_description'] ?? '') ?: 'Show de Premios';
        $bannerTitle = trim($settings['pix_banner_title'] ?? '') ?: 'PAGUE COM PIX DIRETO DO SEU LUGAR';

        $rawShow = $settings['pix_show_on_telao'] ?? 'true';
        $show = !in_array(strtolower((string)$rawShow), ['false', '0', 'no', 'off'], true);

        $normalizedKey = self::normalizePixKey($key, $type);
        $payload = !empty($normalizedKey) ? self::createPayload($key, $receiver, $city, $desc, null, '***', $type) : '';

        return [
            'key' => $key,
            'normalized_key' => $normalizedKey,
            'type' => $type,
            'receiver' => $receiver,
            'city' => $city,
            'description' => $desc,
            'banner_title' => $bannerTitle,
            'show_on_telao' => $show,
            'payload' => $payload,
        ];
    }
}
