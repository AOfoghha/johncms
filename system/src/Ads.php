<?php

/**
 * This file is part of JohnCMS Content Management System.
 *
 * @copyright JohnCMS Community
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0
 * @link      https://johncms.com JohnCMS Project
 */

declare(strict_types=1);

namespace Johncms;

use Johncms\System\Legacy\Tools;
use Johncms\System\Users\User;
use PDO;
use Psr\Container\ContainerInterface;

class Ads
{
    /**
     * @var PDO
     */
    private $db;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Tools
     */
    private $tools;

    private $ads;

    public function __invoke(ContainerInterface $container)
    {
        $this->db = $container->get(PDO::class);
        $this->tools = $container->get(Tools::class);
        $this->user = $container->get(User::class);
        return $this;
    }

    /**
     * Получаем доступные объявления
     *
     * @return array
     */
    public function getAds(): array
    {
        if (! empty($this->ads)) {
            return $this->ads;
        }
        $ads = [];

        $req = $this->db->query("SELECT * FROM `cms_ads` WHERE `to` = '0'");
        while ($res = $req->fetch()) {
            if (! $this->checkAccess($res)) {
                continue;
            }
            $name = explode('|', $res['name']);
            $key = array_rand($name);
            $name = htmlentities($name[$key], ENT_QUOTES, 'UTF-8');

            if (! empty($res['color'])) {
                $name = '<span style="color:#' . $res['color'] . '">' . $name . '</span>';
            }
            // Если было задано начертание шрифта, то применяем
            $font = $res['bold'] ? 'font-weight: bold;' : false;
            $font .= $res['italic'] ? ' font-style:italic;' : false;
            $font .= $res['underline'] ? ' text-decoration:underline;' : false;

            if ($font) {
                $name = '<span style="' . $font . '">' . $name . '</span>';
            }

            $place = $this->getPlace($res);
            $ads[$place][] = '<a href="' . ($res['show'] ? $this->tools->checkout($res['link']) : '/redirect/?id=' . $res['id']) . '">' . $name . '</a><br>';

            if (
                ($res['count_link'] !== 0 && $res['count'] >= $res['count_link']) ||
                ($res['day'] !== 0 && time() >= ($res['time'] + $res['day'] * 3600 * 24))
            ) {
                $this->db->exec('UPDATE `cms_ads` SET `to` = 1  WHERE `id` = ' . $res['id']);
            }
        }

        $this->ads = $ads;
        return $ads;
    }

    /**
     * Получение места размещения рекламной ссылки
     *
     * @param $item
     * @return string
     */
    private function getPlace($item): string
    {
        $places = [
            0 => 'before_menu',
            1 => 'after_menu',
            2 => 'content_header',
            3 => 'content_footer',
        ];

        if (array_key_exists($item['type'], $places)) {
            return $places[$item['type']];
        }
        return '';
    }

    /**
     * Метод проверяет доступность объявления для показа текущему пользователю в текущем месте
     *
     * @param $item
     * @return bool
     */
    private function checkAccess($item): bool
    {
        return (
                empty($item['layout']) ||
                ($item['layout'] === 1 && defined('_IS_HOMEPAGE')) ||
                ($item['layout'] === 2 && ! defined('_IS_HOMEPAGE'))
            ) &&
            (
                empty($item['view']) ||
                ($item['view'] === 1 && ! $this->user->isValid()) ||
                ($item['view'] === 2 && $this->user->isValid())
            );
    }
}