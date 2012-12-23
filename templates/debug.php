<div id="event" title="<?php echo $l->t("Edit an event");?>">
<PRE>
<?php
if (isset($text)) {
    echo htmlspecialchars($text);
}
print_r($_POST);
print_r($_GET);
print_r($_SERVER);
?>
</PRE></div>
