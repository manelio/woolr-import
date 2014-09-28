<?php
namespace Woolr\Component\Connection;

class Db
{
  private function getDouble($n)
  {
    if ($n == 5) return 10;
    return 2*$n;
  }
}