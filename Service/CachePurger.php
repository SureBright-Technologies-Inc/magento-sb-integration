<?php

namespace Surebright\Integration\Service;

use Magento\Framework\App\Cache\TypeListInterface;
use Psr\Log\LoggerInterface;

/**
 * Cleans the Magento cache types relevant to the SureBright integration, so
 * config/permission and storefront-rendering changes take effect without a full
 * `bin/magento cache:flush`.
 *
 * The set covers:
 *  - config / config_integration / config_integration_api / config_webservice —
 *    the module, integration and web-API (custom SureBright API) configuration,
 *    which is what changes when api.xml resources or the integration are updated;
 *  - layout / block_html / full_page — the storefront footer SDK injection and the
 *    admin page rendering.
 */
class CachePurger
{
    /** @var string[] Cache type codes relevant to SureBright. */
    private const RELEVANT_TYPES = [
        'config',
        'config_integration',
        'config_integration_api',
        'config_webservice',
        'layout',
        'block_html',
        'full_page',
    ];

    /** @var TypeListInterface */
    private $cacheTypeList;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        TypeListInterface $cacheTypeList,
        LoggerInterface $logger
    ) {
        $this->cacheTypeList = $cacheTypeList;
        $this->logger = $logger;
    }

    /**
     * Clean the relevant cache types, reporting a per-type result.
     *
     * @return array{success: bool, message: string, steps: array<int, array{step: string, ok: bool, detail: string}>}
     */
    public function purge(): array
    {
        $available = $this->cacheTypeList->getTypes();
        $steps = [];

        foreach (self::RELEVANT_TYPES as $code) {
            if (!isset($available[$code])) {
                // Not every cache type exists on every Magento/Mage-OS build.
                $steps[] = ['step' => $code, 'ok' => true, 'detail' => 'Not present on this install; skipped.'];
                continue;
            }

            $label = $available[$code]->getCacheType() ?: $code;
            try {
                $this->cacheTypeList->cleanType($code);
                $steps[] = ['step' => $label, 'ok' => true, 'detail' => 'Cleaned.'];
            } catch (\Throwable $e) {
                $this->logger->error('SureBright cache purge [' . $code . ']: ' . $e->getMessage());
                $steps[] = ['step' => $label, 'ok' => false, 'detail' => $e->getMessage()];
            }
        }

        $failed = 0;
        foreach ($steps as $step) {
            if (!$step['ok']) {
                $failed++;
            }
        }

        $success = $failed === 0;
        $message = $success
            ? 'SureBright-related caches cleaned.'
            : sprintf('%d cache type(s) failed to clean — see details below.', $failed);

        return ['success' => $success, 'message' => $message, 'steps' => $steps];
    }
}
