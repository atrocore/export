<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore UG (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Export\TemplateLoaders;

use Atro\Core\Twig\AbstractTwigFilter;
use Atro\Core\Twig\AbstractTwigFunction;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigTemplate extends AbstractTemplate
{
    /**
     * @return string
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function render(): string
    {
        if (!empty($template = $this->loadTemplateFromFile())) {
            if ($this->data['feedData']['isTemplateEditable'] && !empty($this->additionalTemplate)) {
                $originTemplateLoader = new ArrayLoader([$this->name => $template]);
                $templateLoader = new ArrayLoader(['template' => $this->additionalTemplate]);

                $loader = new ChainLoader([$originTemplateLoader, $templateLoader]);
            } else {
                $loader = new ArrayLoader(['template' => $template]);
            }

            $twig = new Environment($loader);
        } else {
            $twig = new Environment(new ArrayLoader(['template' => $this->additionalTemplate]));
        }

        // Merge basic filters/functions with export filters/functions
        $this->setupFilters($twig);
        $this->setupFunctions($twig);

        return $twig->render('template', $this->data);
    }

    protected function setupFilters(Environment &$twig): void
    {
        $filters = array_merge($this->getMetadata()->get(['twig', 'filters'], []), $this->getMetadata()->get(['app', 'twigFilters'], []));

        foreach ($filters as $alias => $data) {
            $className = is_array($data) ? $data['handler'] : $data;
            $filter = $this->getContainer()->get($className);
            if ($filter instanceof AbstractTwigFilter) {
                $filter->setTemplateData($this->data);
                $twig->addFilter(new TwigFilter($alias, [$filter, 'filter']));
            }
        }
    }

    protected function setupFunctions(Environment &$twig): void
    {
        $functions = array_merge($this->getMetadata()->get(['twig', 'functions'], []), $this->getMetadata()->get(['app', 'twigFunctions'], []));

        foreach ($functions as $alias => $data) {
            $className = is_array($data) ? $data['handler'] : $data;
            $twigFunction = $this->getContainer()->get($className);
            if ($twigFunction instanceof AbstractTwigFunction && method_exists($twigFunction, 'run')) {
                $twigFunction->setTemplateData($this->data);
                $twig->addFunction(new TwigFunction($alias, [$twigFunction, 'run']));
            }
        }
    }
}
