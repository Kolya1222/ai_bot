<?php

namespace kolya2320\Ai_bot\Models;

use Illuminate\Database\Eloquent\Model;

class AiBotSetting extends Model
{
    protected $table = 'ai_bot_settings';
    protected $fillable = ['key', 'value', 'caption', 'type', 'description', 'category', 'sort_order'];
    private static $encryptionMethod = 'AES-256-CBC';
    private static $encryptionKey = null;
    private static function getEncryptionKey()
    {
        if (self::$encryptionKey === null) {
            $modx = evo();
            $siteKey = $modx->config['site_id'] ?? 'default_site_key';
            self::$encryptionKey = hash('sha256', $siteKey . 'AI_BOT_SECRET_2024', true);
        }
        return self::$encryptionKey;
    }

    private static function generateIv()
    {
        return openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$encryptionMethod));
    }

    private static function encrypt($value)
    {
        if (empty($value)) {
            return $value;
        }
        
        $iv = self::generateIv();
        $encrypted = openssl_encrypt(
            $value,
            self::$encryptionMethod,
            self::getEncryptionKey(),
            0,
            $iv
        );
        
        if ($encrypted === false) {
            return $value;
        }

        return base64_encode($iv) . '::' . $encrypted;
    }

    private static function decrypt($encryptedValue)
    {
        if (empty($encryptedValue) || !str_contains($encryptedValue, '::')) {
            return $encryptedValue;
        }
        
        list($iv, $encrypted) = explode('::', $encryptedValue, 2);
        $iv = base64_decode($iv);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            self::$encryptionMethod,
            self::getEncryptionKey(),
            0,
            $iv
        );
        
        return $decrypted !== false ? $decrypted : $encryptedValue;
    }

    protected $encryptedKeys = ['api_key'];
    
    public function setValueAttribute($value)
    {
        if (in_array($this->key, $this->encryptedKeys) && !empty($value)) {
            if (!$this->isEncrypted($value)) {
                $this->attributes['value'] = self::encrypt($value);
            } else {
                $this->attributes['value'] = $value;
            }
        } else {
            $this->attributes['value'] = $value;
        }
    }

    public function getValueAttribute($value)
    {
        if (in_array($this->key, $this->encryptedKeys) && !empty($value)) {
            return self::decrypt($value);
        }
        
        return $value;
    }

    private function isEncrypted($value)
    {
        return !empty($value) && str_contains($value, '::');
    }

    public static function getValue($key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function getAllGrouped()
    {
        return self::orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('category');
    }

    public function getDisplayValue()
    {
        if ($this->type === 'password' || in_array($this->key, $this->encryptedKeys)) {
            return '••••••••';
        }
        return $this->value;
    }

    public static function getForDisplay()
    {
        return self::orderBy('category')
            ->orderBy('sort_order')
            ->get()
            ->map(function($setting) {
                return [
                    'key' => $setting->key,
                    'caption' => $setting->caption,
                    'type' => $setting->type,
                    'value' => $setting->getDisplayValue(),
                    'description' => $setting->description,
                    'category' => $setting->category,
                    'sort_order' => $setting->sort_order
                ];
            });
    }

    public static function setValue($key, $value, $attributes = [])
    {
        $setting = self::where('key', $key)->first();
        
        if (!$setting) {
            $defaults = [
                'caption' => $key,
                'type' => 'text',
                'category' => 'general',
                'description' => '',
                'sort_order' => 0
            ];
            
            $setting = new self(array_merge($defaults, $attributes));
            $setting->key = $key;
        }
        
        $setting->value = $value;
        
        if (!empty($attributes)) {
            $setting->fill($attributes);
        }
        
        return $setting->save();
    }
}