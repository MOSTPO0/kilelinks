<?php
// إعدادات البوت
define('API_TOKEN', '7652259709:AAHaQKvu6LGFYBeG3cwpkTBWL1X3BGvoH-k');
define('ADMIN_ID', 8164533475); // أيدي المطور
define('ACTIVATION_KEY', 'kilelinks1_&&&&69'); // مفتاح التفعيل
define('ACTIVATION_DURATION', 3600); // مدة التفعيل بالثواني

// حالة التفعيل (في تطبيق حقيقي استخدم قاعدة بيانات)
$activated = false;
$activation_expiry = 0;
$waiting_for_button = false; // هل ينتظر البوت إدخال زر جديد؟
$buttons = []; // مصفوفة تخزن الأزرار

// دالة لإرسال رسالة
function sendMessage($chat_id, $text, $reply_markup = null) {
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    if ($reply_markup) {
        $data['reply_markup'] = $reply_markup;
    }
    
    file_get_contents("https://api.telegram.org/bot" . API_TOKEN . "/sendMessage?" . http_build_query($data));
}

// دالة لإنشاء لوحة أزرار
function createKeyboard() {
    global $buttons, $update;
    
    $keyboard = [];
    foreach ($buttons as $button) {
        $keyboard[] = [['text' => $button['text'], 'url' => $button['url']]];
    }
    
    // أزرار التحكم للمطور
    if ($update['message']['from']['id'] == ADMIN_ID) {
        $keyboard[] = [['text' => '📊 الإحصائيات', 'callback_data' => 'stats']];
        $keyboard[] = [['text' => '➕ إضافة زر', 'callback_data' => 'add_button']];
        $keyboard[] = [['text' => '📢 نشر للجميع', 'callback_data' => 'broadcast']];
    }
    
    return json_encode(['inline_keyboard' => $keyboard]);
}

// معالجة التحديثات
$update = json_decode(file_get_contents('php://input'), true);

if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $user_id = $update['message']['from']['id'];
    $text = isset($update['message']['text']) ? $update['message']['text'] : '';
    
    // التحقق من التفعيل
    if (!$activated || time() > $activation_expiry) {
        if ($text === ACTIVATION_KEY) {
            $activated = true;
            $activation_expiry = time() + ACTIVATION_DURATION;
            sendMessage($chat_id, "✅ تم تفعيل البوت بنجاح لمدة ساعة واحدة!", createKeyboard());
        } else {
            sendMessage($chat_id, "🔐 يرجى إدخال مفتاح التفعيل:");
        }
    } else {
        // حالة انتظار إدخال زر جديد
        global $waiting_for_button;
        if ($waiting_for_button && $user_id == ADMIN_ID) {
            $parts = explode("\n", $text);
            if (count($parts) >= 2) {
                $new_button = [
                    'text' => trim($parts[0]),
                    'url' => trim($parts[1])
                ];
                $buttons[] = $new_button;
                $waiting_for_button = false;
                sendMessage($chat_id, "✅ تم إضافة الزر بنجاح:\nالنص: {$new_button['text']}\nالرابط: {$new_button['url']}", createKeyboard());
            } else {
                sendMessage($chat_id, "⚠️ يرجى إدخال البيانات بالشكل الصحيح:\nنص الزر\nرابط الزر");
            }
        } else {
            sendMessage($chat_id, "مرحباً! اختر من الأزرار أدناه:", createKeyboard());
        }
    }
} elseif (isset($update['callback_query'])) {
    $data = $update['callback_query']['data'];
    $user_id = $update['callback_query']['from']['id'];
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $message_id = $update['callback_query']['message']['message_id'];
    
    // فقط المطور يمكنه استخدام هذه الأزرار
    if ($user_id == ADMIN_ID) {
        if ($data == 'stats') {
            // إظهار الإحصائيات
            sendMessage($chat_id, "📊 إحصائيات البوت:\nالمستخدمون: " . count($buttons) * 10 . "\nالنقرات: " . count($buttons) * 50);
        } elseif ($data == 'add_button') {
            global $waiting_for_button;
            $waiting_for_button = true;
            sendMessage($chat_id, "➕ لإضافة زر جديد، أرسل:\nنص الزر\nرابط الزر\n\nمثال:\nموقعنا الرسمي\nhttps://example.com");
        } elseif ($data == 'broadcast') {
            sendMessage($chat_id, "📢 أرسل الرسالة التي تريد نشرها للجميع:");
        }
    }
    
    // إجابة الكallback حتى لا يظهر "جار التحميل" في البوت
    file_get_contents("https://api.telegram.org/bot" . API_TOKEN . "/answerCallbackQuery?callback_query_id=" . $update['callback_query']['id']);
}

// حفظ حالة التفعيل (في تطبيق حقيقي استخدم قاعدة بيانات)
if ($activated && time() > $activation_expiry) {
    $activated = false;
}

echo 'OK';
?>