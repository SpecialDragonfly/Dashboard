<?php
namespace Dashboard\Areas;

interface AreaInterface
{
    public function toHtml(): string;
    public function htmlId() : string;
    public function areaTitle(): string;
}