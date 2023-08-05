<?php
namespace Dashboard;

class ServerDebugging
{
    private $checkPoints = [
        "uptime # uptime and CPU stress",
        "w # or better yet:last |head # who is/has been in",
        "netstat -tlpn # find server role",
        "df -h # out of disk space?",
        "df -hi #checking for inode availability",
        "grep kill /var/log/messages # out of memory?",
        "ps auxf # what's running",
        "htop # stressed? , look out for D (waiting on I/O typically) processes",
        "history # what has changed recently",
        "tail /var/log/application.log # anything interesting logged?"
    ];

    public function toHtml() {
        $html = "<p>My goto for initially troubleshooting a server is:</p>";
        $html .= "<ol>";
        foreach ($this->checkPoints as $point) {
            $html .= "<li>".$point."</li>";
        }
        $html .= "</ol>";

        return $html;
    }
}