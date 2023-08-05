<?php
namespace Dashboard;

class Html
{
    public function out($msg) {
        echo "<html>".$msg."</html>";
    }
}