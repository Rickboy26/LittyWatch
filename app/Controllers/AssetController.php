<?php
declare(strict_types=1);

namespace LittyWatch\Controllers;
use LittyWatch\Market\StrictCatalogGate;

use LittyWatch\Core\Request;
use LittyWatch\Core\Response;
use LittyWatch\Core\View;
use LittyWatch\V2\Assets\AssetCatalogService;
use Throwable;

final class AssetController
{
    public function __construct(
        private readonly AssetCatalogService $assets,
        private readonly View $view,
        private readonly string $root,
    ) {}

    public function index(Request $request): Response
    {
        return $this->page($request);
    }

    public function autoLink(Request $request): Response
    {
        try {
            $raw=$request->string('payload');
            if($raw==='') throw new \RuntimeException('Geen automatische koppelingen ontvangen.');
            $matches=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
            if(!is_array($matches)) throw new \RuntimeException('Ongeldige mapper payload.');
            $result=$this->assets->bulkAutoLink($matches);
            return Response::json(['ok'=>true]+$result+['summary'=>$this->assets->summary()]);
        } catch (Throwable $e) {
            return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);
        }
    }

    public function wikiIcon(Request $request): Response
    {
        $file=trim($request->string('file'));
        $file=preg_replace('/^File:/i','',$file) ?? $file;
        $remote=trim($request->string('url'));
        $resolvedIp=trim($request->string('ip'));

        if($file===''||strlen($file)>180||!preg_match('/^[^\\\/\x00-\x1F]+\.png$/iu',$file)){
            return Response::json(['ok'=>false,'error'=>'Ongeldige Wiki-iconnaam.'],400);
        }

        $cacheDir=$this->root.'/storage/cache/wiki-inventory-icons';
        if(!is_dir($cacheDir)) @mkdir($cacheDir,0775,true);
        $cacheFile=$cacheDir.'/'.sha1(mb_strtolower($file)).'.png';
        if(is_file($cacheFile)&&filesize($cacheFile)>32){
            $body=(string)file_get_contents($cacheFile);
            if(str_starts_with($body,"\x89PNG\r\n\x1a\n")){
                return new Response($body,200,['Content-Type'=>'image/png','Cache-Control'=>'public, max-age=604800','X-LittyWatch-Icon-Source'=>'cache']);
            }
        }

        $url='https://wiki.guildwars.com/wiki/Special:Redirect/file/'.rawurlencode($file);
        if($remote!==''){
            $parts=@parse_url($remote);
            $host=strtolower((string)($parts['host']??''));
            $scheme=strtolower((string)($parts['scheme']??''));
            $path=(string)($parts['path']??'');
            if($scheme==='https'&&$this->allowedWikiHost($host)&&$path!=='') $url=$remote;
        }

        if($resolvedIp!==''&&!filter_var($resolvedIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4|FILTER_FLAG_IPV6)) $resolvedIp='';
        $parts=@parse_url($url);
        $targetHost=strtolower((string)($parts['host']??'wiki.guildwars.com'));
        if(!$this->allowedWikiHost($targetHost)){
            return Response::json(['ok'=>false,'error'=>'Niet-toegestane Wiki-host.','host'=>$targetHost],400);
        }

        $attempts=[];
        $result=$this->fetchWikiIconCurl($url,$targetHost,$resolvedIp);
        $attempts[]=$result['debug'];
        if(!$result['ok']&&$resolvedIp!==''){
            $socket=$this->fetchWikiIconSocket($url,$targetHost,$resolvedIp);
            $attempts[]=$socket['debug'];
            if($socket['ok']) $result=$socket;
        }

        if(!$result['ok']){
            return Response::json([
                'ok'=>false,
                'error'=>'Wiki inventory icon kon niet via de server worden gelezen.',
                'file'=>$file,
                'url'=>$url,
                'host'=>$targetHost,
                'resolved_ip'=>$resolvedIp,
                'attempts'=>$attempts,
            ],502);
        }

        $body=(string)$result['body'];
        if(is_dir($cacheDir)&&is_writable($cacheDir)) @file_put_contents($cacheFile,$body,LOCK_EX);
        return new Response($body,200,[
            'Content-Type'=>'image/png',
            'Cache-Control'=>'public, max-age=604800',
            'X-LittyWatch-Icon-Source'=>(string)$result['source'],
        ]);
    }

    private function allowedWikiHost(string $host): bool
    {
        $host=strtolower(trim($host));
        return $host==='wiki.guildwars.com'||$host==='wiki-en.guildwars.com'||str_ends_with($host,'.guildwars.com');
    }

    /** @return array{ok:bool,body:string,source:string,debug:array<string,mixed>} */
    private function fetchWikiIconCurl(string $url,string $host,string $resolvedIp): array
    {
        $body='';$status=0;$contentType='';$curlError='';$errno=0;$effective='';
        if(!function_exists('curl_init')){
            return ['ok'=>false,'body'=>'','source'=>'curl','debug'=>['method'=>'curl','error'=>'curl_ext_missing']];
        }
        $ch=curl_init($url);
        if($ch===false){
            return ['ok'=>false,'body'=>'','source'=>'curl','debug'=>['method'=>'curl','error'=>'curl_init_failed']];
        }
        $opts=[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>22,
            CURLOPT_USERAGENT=>'Mozilla/5.0 (compatible; LittyWatch/5.2; +https://hollandseglory.nl)',
            CURLOPT_REFERER=>'https://wiki.guildwars.com/',
            CURLOPT_HTTPHEADER=>[
                'Accept: image/avif,image/webp,image/apng,image/png,image/*;q=0.8,*/*;q=0.5',
                'Accept-Language: en-US,en;q=0.9',
                'Connection: close',
            ],
            CURLOPT_ENCODING=>'',
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ];
        if(defined('CURL_HTTP_VERSION_1_1')) $opts[CURLOPT_HTTP_VERSION]=CURL_HTTP_VERSION_1_1;
        if($resolvedIp!=='') $opts[CURLOPT_RESOLVE]=[$host.':443:'.$resolvedIp];
        curl_setopt_array($ch,$opts);
        $raw=curl_exec($ch);
        if(is_string($raw)) $body=$raw;
        $status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);
        $contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);
        $effective=(string)curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
        $curlError=(string)curl_error($ch);
        $errno=(int)curl_errno($ch);
        curl_close($ch);
        $isPng=$body!==''&&str_starts_with($body,"\x89PNG\r\n\x1a\n");
        return [
            'ok'=>$status>=200&&$status<300&&$isPng,
            'body'=>$body,
            'source'=>$resolvedIp!==''?'resolved-ip-curl':'server-dns-curl',
            'debug'=>[
                'method'=>'curl','status'=>$status,'content_type'=>$contentType,'bytes'=>strlen($body),
                'errno'=>$errno,'error'=>$curlError,'effective_url'=>$effective,'png'=>$isPng,
            ],
        ];
    }

    /** @return array{ok:bool,body:string,source:string,debug:array<string,mixed>} */
    private function fetchWikiIconSocket(string $url,string $host,string $resolvedIp): array
    {
        $parts=@parse_url($url);
        $path=(string)($parts['path']??'/');
        $query=(string)($parts['query']??'');
        if($query!=='') $path.='?'.$query;
        $ctx=stream_context_create(['ssl'=>[
            'verify_peer'=>true,'verify_peer_name'=>true,'peer_name'=>$host,'SNI_enabled'=>true,
        ]]);
        $errno=0;$errstr='';
        $socket=@stream_socket_client('ssl://'.$resolvedIp.':443',$errno,$errstr,10,STREAM_CLIENT_CONNECT,$ctx);
        if(!is_resource($socket)){
            return ['ok'=>false,'body'=>'','source'=>'resolved-ip-socket','debug'=>['method'=>'socket','errno'=>$errno,'error'=>$errstr]];
        }
        stream_set_timeout($socket,18);
        $request="GET ".$path." HTTP/1.1\r\nHost: ".$host."\r\nUser-Agent: Mozilla/5.0 (compatible; LittyWatch/5.2)\r\nAccept: image/png,image/*;q=0.9,*/*;q=0.1\r\nReferer: https://wiki.guildwars.com/\r\nConnection: close\r\n\r\n";
        fwrite($socket,$request);
        $raw='';
        while(!feof($socket)){
            $chunk=fread($socket,65536);
            if($chunk===false) break;
            $raw.=$chunk;
            if(strlen($raw)>2_000_000) break;
        }
        $meta=stream_get_meta_data($socket);
        fclose($socket);
        $split=strpos($raw,"\r\n\r\n");
        $headers=$split===false?$raw:substr($raw,0,$split);
        $body=$split===false?'':substr($raw,$split+4);
        preg_match('/^HTTP\/\S+\s+(\d{3})/',$headers,$m);
        $status=(int)($m[1]??0);
        if(preg_match('/\r\nTransfer-Encoding:\s*chunked/i',$headers)) $body=$this->decodeChunkedBody($body);
        $isPng=$body!==''&&str_starts_with($body,"\x89PNG\r\n\x1a\n");
        return [
            'ok'=>$status>=200&&$status<300&&$isPng,
            'body'=>$body,
            'source'=>'resolved-ip-socket',
            'debug'=>[
                'method'=>'socket','status'=>$status,'bytes'=>strlen($body),'png'=>$isPng,
                'timed_out'=>(bool)($meta['timed_out']??false),'headers'=>substr(str_replace("\r\n",' | '),0,500),
            ],
        ];
    }

    private function decodeChunkedBody(string $body): string
    {
        $out='';$offset=0;$len=strlen($body);
        while($offset<$len){
            $lineEnd=strpos($body,"\r\n",$offset);
            if($lineEnd===false) break;
            $hex=trim(substr($body,$offset,$lineEnd-$offset));
            $semi=strpos($hex,';');if($semi!==false)$hex=substr($hex,0,$semi);
            if($hex===''||!ctype_xdigit($hex)) break;
            $size=hexdec($hex);$offset=$lineEnd+2;
            if($size===0) break;
            $out.=substr($body,$offset,$size);$offset+=$size+2;
        }
        return $out;
    }








    public function enforceStrictCatalog(Request $request): Response
    {
        try {
            $result=(new StrictCatalogGate(db()))->quarantineExisting();
            return Response::json(['ok'=>true]+$result);
        } catch(Throwable $e){return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);}
    }

    public function namedCoverage(Request $request): Response
    {
        try {
            $q=trim($request->string('q'));
            return Response::json(['ok'=>true]+$this->assets->namedCoverage()+[
                'missing'=>$this->assets->missingNamedAssets($q,1000)
            ]);
        } catch(Throwable $e){return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);}
    }

    public function importNamedAsset(Request $request): Response
    {
        try {
            $name=trim($request->string('name'));$category=trim($request->string('category'));
            $encoded=$request->string('png');
            if($name===''||$encoded==='')throw new \RuntimeException('Naam of PNG ontbreekt.');
            if(str_contains($encoded,','))$encoded=substr($encoded,strpos($encoded,',')+1);
            $binary=base64_decode($encoded,true);
            if($binary===false||strlen($binary)>2_000_000)throw new \RuntimeException('Ongeldige PNG data.');
            return Response::json(['ok'=>true]+$this->assets->saveNamedAsset($name,$category,$binary)+$this->assets->namedAssetSummary());
        } catch(Throwable $e){return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);}
    }

    public function importGwcaIds(Request $request): Response
    {
        try {
            $rows=json_decode($request->string('rows'),true,512,JSON_THROW_ON_ERROR);
            if(!is_array($rows))throw new \RuntimeException('Ongeldige GWCA data.');
            return Response::json(['ok'=>true]+$this->assets->importGameModelIds($rows)+['game_ids'=>$this->assets->gameIdSummary()]);
        } catch(Throwable $e){return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);}
    }

    public function importRuntimeIds(Request $request): Response
    {
        try {
            $rows=json_decode($request->string('rows'),true,512,JSON_THROW_ON_ERROR);
            if(!is_array($rows))throw new \RuntimeException('Ongeldige runtime export.');
            return Response::json(['ok'=>true]+$this->assets->importRuntimeFileIds($rows)+['game_ids'=>$this->assets->gameIdSummary()]);
        } catch(Throwable $e){return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);}
    }

    public function cleanupKnowledge(Request $request): Response
    {
        try {
            $result=$this->assets->knowledgeCleanup();
            return Response::json(['ok'=>true]+$result+['summary'=>$this->assets->summary()]);
        } catch (Throwable $e) {
            return Response::json(['ok'=>false,'error'=>$e->getMessage()],400);
        }
    }

    public function update(Request $request): Response
    {
        $message=null;$error=null;
        try {
            $action=$request->string('action','link');
            $assetId=$request->int('asset_id');
            if($assetId<=0) throw new \RuntimeException('Geen geldig icoon gekozen.');
            if($action==='unlink') {
                $this->assets->unlink($assetId);
                $message='Icoonkoppeling verwijderd.';
            } else {
                $item=$request->string('item');
                if($item==='') throw new \RuntimeException('Kies eerst een item.');
                $this->assets->link($assetId,$item);
                $message='Icoon is aan het marktitem gekoppeld.';
            }
        } catch (Throwable $e) { $error=$e->getMessage(); }
        return $this->page($request,$message,$error);
    }

    private function page(Request $request,?string $message=null,?string $error=null): Response
    {
        $directory=$this->root.'/assets/game-items';
        $q=trim($request->string('q'));
        $filter=$request->string('filter','all');
        if(!in_array($filter,['all','linked','unlinked'],true))$filter='all';
        $page=max(1,$request->int('page',1));
        $limit=72;$offset=($page-1)*$limit;
        $summary=$this->assets->summary();
        $rows=$this->assets->assets($q,$filter,$limit,$offset);
        return Response::html($this->view->render('assets/index',[
            'title'=>'Inventory icons · LittyWatch',
            'summary'=>$summary,
            'directory'=>$directory,
            'assets'=>$rows,
            'items'=>$this->assets->marketItems('',3000),
            'autoItems'=>$this->assets->unlinkedMarketItems(3000),
            'q'=>$q,'filter'=>$filter,'page'=>$page,'limit'=>$limit,
            'message'=>$message,'error'=>$error,
        ]));
    }
}
