<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 108px 46px 56px; }
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; line-height: 1.6; }
        h1 { font-size: 22px; margin-bottom: 28px; text-align: center; }
        h2 { font-size: 15px; margin-top: 28px; margin-bottom: 10px; }
        h3 { font-size: 13px; margin-top: 22px; margin-bottom: 8px; }
        p { margin: 0 0 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; line-height: 1.35; margin: 14px 0; }
        th, td { border: 1px solid #d1d5db; padding: 8px 10px; vertical-align: top; }
        th { width: 34%; text-align: left; font-weight: normal; background: #f9fafb; }
        ul, ol { margin: 0 0 12px 24px; padding: 0; }
        blockquote { margin: 14px 0; padding-left: 14px; border-left: 3px solid #d1d5db; color: #374151; }
        .amount { font-weight: bold; }
        .social-summary { width: 100%; margin-top: 28px; border-collapse: collapse; font-size: 12px; line-height: 1.35; }
        .social-summary th, .social-summary td { border: 1px solid #d1d5db; padding: 7px 9px; }
        .social-summary th { width: 72%; text-align: left; font-weight: normal; background: #f9fafb; }
        .social-summary td { width: 28%; text-align: right; font-weight: bold; }
        .social-summary-spacer td { padding: 5px 0; border-left: 0; border-right: 0; background: #ffffff; }
        .signature-footer { margin-top: 80px; display: flex; justify-content: space-between; }
        .document-header { position: fixed; top: -82px; left: 0; right: 0; height: 62px; border-bottom: 1px solid #d1d5db; }
        .document-header-logo { float: left; max-height: 50px; max-width: 150px; margin-right: 16px; }
        .document-header-text { font-size: 10px; line-height: 1.35; color: #374151; white-space: normal; }
    </style>
</head>
<body>
    @include('pdf.partials.document-header')
    @include('pdf.partials.document-footer')

    {!! $content !!}
</body>
</html>
