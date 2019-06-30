<?php

namespace Hn\Video\ViewHelpers;


use Hn\Video\Processing\VideoTaskRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

class ProgressViewHelper extends AbstractViewHelper
{
    const POLLING_INTERVAL = 15;
    const MAX_PREDICTED_PROGRESS = 20;
    private static $counter = 0;

    protected $escapeChildren = false;
    protected $escapeOutput = false;

    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('file', 'int', 'File', true);
        $this->registerArgument('configuration', 'array', 'Task configuration', true);
    }

    public static function renderStatic(array $arguments, \Closure $renderChildrenClosure, RenderingContextInterface $renderingContext)
    {
        return static::renderHtml($arguments['file'], $arguments['configuration']);
    }

    public static function renderHtml(int $file, array $configuration)
    {
        $task = GeneralUtility::makeInstance(VideoTaskRepository::class)->findByFile($file, $configuration);
        if ($task->getLastProgress() <= 0) {
            return '';
        }

        $id = 'tx_video_progress_' . self::$counter++;
        $attributes = ['id="' . $id . '"'];
        $parameters = ProgressEid::parameters($task);
        $parameters['updateUrl'] = ProgressEid::getUrl($file, $configuration);
        foreach ($parameters as $key => $value) {
            $attributeName = "data-" . strtolower(preg_replace('#[A-Z]#', '-\0', $key));
            $attributeValue = '"' . htmlspecialchars($value) . '"';
            $attributes[] = "$attributeName=$attributeValue";
        }

        $content = '<code ' . implode(' ', $attributes) . '>';
        if ($task->getLastProgress() >= 1.0) {
            $content .= '100.0%';
            $content .= '</code>';
        } else {
            $content .= '<script>' . self::renderJavaScript($id) . '</script>';
        }

        return $content;
    }

    private static function renderJavaScript(string $id)
    {
        $jsonId = json_encode($id, JSON_UNESCAPED_SLASHES);
        $pollingInterval = json_encode(self::POLLING_INTERVAL * 1000, JSON_UNESCAPED_SLASHES);
        $maxPredictedProgress = json_encode(self::MAX_PREDICTED_PROGRESS * 1000, JSON_UNESCAPED_SLASHES);
        $script = <<<JavaScript
(function () {
    var element = document.getElementById($jsonId),
        p = 0.0, r = 0, s = 0,
        updateProperties = function (o) {
            p = Number(o.progress);
            r = Number(o.remaining);
            s = Number(o.lastUpdate);
        },
        lastContent = element.textContent,
        updateTimeout = 0,
        requestProperties = function () {
            var xhr = new XMLHttpRequest();
            xhr.onload = function () {
                updateProperties(JSON.parse(xhr.responseText));
                updateTimeout = setTimeout(requestProperties, $pollingInterval);
            };
            xhr.open('GET', element.dataset.updateUrl, true);
            xhr.send();
        },
        render = function () {
            // check if the target node is still within the document and stop everything if not
            if (document.getElementById($jsonId) !== element) {
                clearTimeout(updateTimeout);
                return;
            }
        
            // calculate the progress until it should be finished
            var progress = Math.min(1.0, Math.min($maxPredictedProgress, Date.now() - s) / r),
                newContent = ((p + (1.0 - p) * progress) * 100).toFixed(1) + '%';
            if (lastContent !== newContent) {
                element.textContent = newContent;
                lastContent = newContent;
            }
            
            if (progress < 1.0) {
                setTimeout(render, Math.max(100, r / (1.0 - p) / 1000));
            } else {
                clearTimeout(updateTimeout);
                setTimeout(function () {
                    if (!window.video_is_reloading) {
                        location.reload();
                        window.video_is_reloading = true;
                    }
                }, 5000);
            }
        }
    ;
    updateProperties(element.dataset);
    setTimeout(render, 0);
    updateTimeout = setTimeout(requestProperties, $pollingInterval);
})();
JavaScript;

        // minify a bit
        return preg_replace('#\s*\n\s*|\s*//[^\n]*\s*|\s*([,;!=()*/\n+-])\s*#', '\\1', $script);
    }
}
