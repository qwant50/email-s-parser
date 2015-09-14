<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
</head>

<body>

<?php
function get_imap_connection()
{

    $mbox = imap_open("{xxx.xxx.xxx.xxx:xxx/pop3/novalidate-cert}INBOX", "xml", "password") or die("can't connect: " . imap_last_error());
    if ($mbox) {                   // call this to avoid the mailbox is empty error message
        if (imap_num_msg($mbox) == 0) {
            $errors = imap_errors();
            if ($errors) {
                die("can't connect: " . imap_last_error());
            }
        }
    }
    return $mbox;
}

function get_msg($imap_box, $email_number, $mime_type, $structure = false, $part_number = false)
{
    if (!$structure) {  // ПЕРВЫЙ запуск, получение корня структуры
        $structure = imap_fetchstructure($imap_box, $email_number);

    }
    $message = "";
    if ($structure->subtype == $mime_type) {//"CHARSET")
        if(!$part_number) $part_number = "1";
        $data = imap_fetchbody($imap_box, $email_number, $part_number);  //получить конкретную часть письма
        if ($structure->encoding == 3) $data = base64_decode($data);
        if ($structure->encoding == 4) $data = imap_qprint($data);
        if ($structure->parameters[0]->value == "windows-1251") $data = mb_convert_encoding($data, 'utf-8', 'windows-1251');
        if ($structure->parameters[0]->value == "koi8-r") $data = mb_convert_encoding($data, 'utf-8', 'koi8-r');
        //echo "<br> Вложенная часть: " . $part_number . "<br>";
        $message .= $data;
    }
    // Если письмо состоит из многа частей - разбираем каждую отдельно
    if ($structure->parts) {
        while (list($index, $sub_structure) = each($structure->parts)) {
            if($part_number) {
                $prefix = $part_number . '.';
            }
            $message .= get_msg($imap_box, $email_number, $mime_type, $sub_structure, $prefix . ($index + 1));
        }// end while
    }  // end if
    return $message;
}

function get_exchange($imap_box) {

    $emails = imap_search($imap_box, "FROM 'e-mail address'");  //ищем письма от 
//$emails = imap_search($imap_box, "FROM 'e-mail address'");  //ищем письма от 
//echo "<br>";
//if ($emails) echo "Найдено:" . sizeof($emails) . " писем.<br>";  // сколько нашли и какие их номера начиная с 0
//else echo "Писем удовлетворяющих условию не найдено.";
    $result = false;

    if ($emails) {

        /* put the newest emails on top */
        rsort($emails);

        /* for every email... */
        foreach ($emails as $email_number) {
            $message = get_msg($imap_box, $email_number, "PLAIN");  //  PLAIN or  HTML  or ...

            $message = preg_replace('/\r\n|\r|\n/u', '', $message);  // удаляем символы перевода строки
            preg_match("/Курс виписки рахунків- \d\d\,\d\dГотівковий- \d\d\,\d\d/", $message, $output_array); //ищем паттерн
            if ($output_array[0]) {  // Если нашли паттерн
                if (!$result) preg_match_all('/\d\d\,\d\d/', $output_array[0], $result); //нашли свежак и сохраняем его
                imap_delete($imap_box,$email_number);  //помечаем письмо на удаление
            }
        }
    }
    imap_expunge($imap_box);
    return $result[0];

}


if ($imap_box = get_imap_connection()) {
    $result = get_exchange($imap_box);
    imap_close($imap_box);
}
if ($result) echo "Нал: ".$result[0]." БН: ".$result[1];

?>

</body>
</html>

