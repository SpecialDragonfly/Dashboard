<?php
namespace Dashboard\Areas;

class BlogRoll implements AreaInterface
{
    /**
     * @var string[]
     */
    private $blogs;

    /**
     * BlogRoll constructor.
     * @param array $blogs
     */
    public function __construct(array $blogs)
    {
        $this->blogs = $blogs;
    }

    public function toHtml(): string
    {
        $html = "<ol>";
        foreach ($this->blogs as $blogName => $blogUrl) {
            $html .= '<li><a href="'.$blogUrl.'" target="_blank">'.$blogName.'</a></li>';
        }
        $html .= "</ol>";

        return $html;
    }

    public function htmlId(): string
    {
        return 'blog-roll';
    }

    public function areaTitle(): string
    {
        return 'Blogs';
    }
}