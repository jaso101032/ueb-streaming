<x-app-layout>
    <div data-react-page="WatermarkSettings" data-props="{{ json_encode([
        'watermark' => $watermark,
        'updateUrl' => route('admin.watermark.update'),
        'resetUrl' => route('admin.watermark.destroy'),
    ]) }}"></div>
</x-app-layout>
