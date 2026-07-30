<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor\Plugins\Contracts\RichContentPlugin;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Illuminate\Support\Facades\Vite;
use Tiptap\Core\Extension;

class RichEditorTableEditingPlugin implements RichContentPlugin
{
    /** @return array<Extension> */
    public function getTipTapPhpExtensions(): array
    {
        return [];
    }

    /** @return array<string> */
    public function getTipTapJsExtensions(): array
    {
        // The table-editing extension is a progressive enhancement. If the
        // asset is missing from the Vite manifest (deploy pulled the code but
        // `npm run build` didn't run or failed), Vite::asset() THROWS — which
        // would 500 every admin page containing a rich editor ("There was an
        // error while attempting to load this page"). Degrade to no extension
        // instead: the editor still works, tables just lose the click-to-caret
        // helper until the next successful build.
        try {
            return [Vite::asset('resources/js/filament-rich-editor-table-editing.js')];
        } catch (\Throwable $e) {
            \App\Models\ErrorLog::record($e);

            return [];
        }
    }

    /** @return array<RichEditorTool> */
    public function getEditorTools(): array
    {
        return [];
    }

    /** @return array<Action> */
    public function getEditorActions(): array
    {
        return [];
    }
}
