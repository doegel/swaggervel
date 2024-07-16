<?php namespace Doegel\Swaggervel\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Routing\Controller;

class SwaggervelController extends Controller
{
    public function definitions($page = 'api-docs.yaml')
    {
        if (config('swaggervel.auto-generate')) {
            $this->regenerateDefinitions();
        }

        $filePath = config('swaggervel.doc-dir') . "/{$page}";

        if (File::extension($filePath) === "") {
            $filePath .= '.yaml';
        }

        if (!File::exists($filePath)) {
            app()->abort(404, "Cannot find {$filePath}");
        }

        $content = File::get($filePath);

        return response($content, 200, array(
            'Content-Type' => 'text/yaml'
        ));
    }

    public function ui(Request $request)
    {
        if (config('swaggervel.auto-generate')) {
            $this->regenerateDefinitions();
        }

        if (config('swaggervel.behind-reverse-proxy')) {
            $proxy = $request->server('REMOTE_ADDR');
            $request->setTrustedProxies(array($proxy));
        }

        //need the / at the end to avoid CORS errors on Homestead systems.
        return response()
            ->view('swaggervel::index', [
                'urlToDocs' => config('swaggervel.doc-route'),
                'clientId' => config('swaggervel.client-id'),
                'clientSecret' => config('swaggervel.client-secret'),
                'realm' => config('swaggervel.realm'),
                'appName' => config('swaggervel.app-name'),
                'initOAuth' => config('swaggervel.init-o-auth'),
                'scopeSeparator' => config('swaggervel.scope-separator'),
                'additionalQueryStringParams' => json_encode(config('swaggervel.additional-query-string-params'), JSON_FORCE_OBJECT),
                'useBasicAuthenticationWithAccessCodeGrant' => config('swaggervel.use-basic-auth-with-access-code-grant') ? 'true' : 'false',
                'uiResourcePath' => config('swaggervel.ui-resource-path'),
                'host' => $this->makeHost(),
            ])
            ->withHeaders(config('swaggervel.view-headers'));
    }

    private function regenerateDefinitions()
    {
        $dir = config('swaggervel.app-dir');
        if (is_array($dir)) {
            $appDir = [];
            foreach ($dir as $d) {
                $appDir[] = base_path($d);
            }
        } else {
            $appDir = base_path($dir);
        }

        $docDir = config('swaggervel.doc-dir');

        if (!File::exists($docDir)) {
            File::makeDirectory($docDir);
        }

        if (is_writable($docDir)) {
            $excludeDirs = config('swaggervel.excludes');

            $swagger = \OpenApi\Generator::scan(
                \OpenApi\Util::finder($appDir, $excludeDirs)
            );

            $filename = $docDir . '/api-docs.yaml';
            file_put_contents($filename, $swagger->toYAML());
        }
    }

    private function makeHost() {

        if (config('swaggervel.secure-protocol')) {
            return secure_url('');
        }
        else {
            return url('');
        }
    }
}
