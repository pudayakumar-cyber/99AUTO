<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class VehiclePickerPopupTest extends TestCase
{
    public function test_the_automatic_vehicle_picker_prompt_is_temporarily_disabled(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/master/front.blade.php');

        $this->assertStringContainsString(
            '// const shouldAutoPrompt = @json($shouldAutoPromptVehicle);',
            $view
        );
        $this->assertStringContainsString('const shouldAutoPrompt = false;', $view);
    }

    public function test_manual_vehicle_picker_controls_remain_available(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/master/front.blade.php');

        $this->assertStringContainsString("toggleBtn.addEventListener('click'", $view);
        $this->assertStringContainsString("searchBtn.addEventListener('click'", $view);
    }

    public function test_storefront_script_url_changes_when_the_asset_changes(): void
    {
        $view = file_get_contents(__DIR__.'/../../resources/views/master/front.blade.php');

        $this->assertStringContainsString("filemtime(\$storefrontScriptPath)", $view);
        $this->assertStringContainsString(
            "myscript.js') }}?v={{ \$storefrontScriptVersion }}",
            $view
        );
    }
}
