<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Dompdf\Dompdf;
use Illuminate\Support\Str;


use App\Models\Article;
use App\Models\Publication;
use App\Services\UtilsService;

class ArticleController extends Controller
{
    //
    public function Search(Request $request) {
        $query = Article::select('*')
        ->join('publication', 'article.id', '=', 'publication.idarticle');
        if ($request->filled('categorie')) {
            $query->where('categorie', 'like', '%' . $request->input('categorie') . '%');
        }

        if ($request->filled('texte')) {
            $query->where(function($q) use ($request) {
                $q->where('titre', 'like', '%' . $request->input('texte') . '%')
                ->orWhere('resume', 'like', '%' . $request->input('texte') . '%')
                ->orWhere('contenu', 'like', '%' . $request->input('texte') . '%');
            });
        }

        if ($request->filled('publish_at_1')) {
            $query->where('publication.publish_at', '>=', $request->input('publish_at_1'));
        }

        if ($request->filled('publish_at_2')) {
            $query->where('publication.publish_at', '<=', $request->input('publish_at_2'));
        }
        $articles = $query->paginate(6);
        return view('Search',[
            'liste_article' => $articles,
            'links' => $articles->links()
        ]);
    }

    public function getDetails($slug) {
        $file_without_extension = basename($slug, ".html"); // renvoie "un-titre-très-long_unid"
        $idarticle = substr(strrchr($file_without_extension, "_"), 1);
        $article = Article::find($idarticle);
        if($article!==null) {
            return view('Details',['article' => $article]);
        }
        else abort(404);
    }

    /*-- article --*/
    public function UpdateArticle(Request $request) {
        $data = $request->all();
        $article = Article::find($request->input('id'));
        if($request->hasFile('other_image')) {
            $file = $request->file('other_image');
            if ($file->isValid() && strpos($file->getClientMimeType(), 'image/') === 0) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/img'), $filename);
                $data['image'] = 'assets/img/' . $filename;
            }
        }
        unset($data['none']);
        unset($data['other_image']);
        $article->fill($data);
        $article->save();
        $publication = $article->getPublication();
        date_default_timezone_set('Indian/Antananarivo');
        $publication->update_at = date('Y-m-d H:i:s');
        $publication->save();
        return redirect()->back()->with('success','Modification enregistrée.');
    }

    public function CreateArticle(Request $request) {
        $data = [
            "categorie" => $request->input('categorie'),
            "titre" => $request->input('titre'),
            "resume" => $request->input('resume'),
            "image" => "assets/img/default.jpg",
            "contenu" => $request->input("contenu"),
            "idauteur" => session('author')
        ];
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            if ($file->isValid() && strpos($file->getClientMimeType(), 'image/') === 0) {
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('assets/img'), $filename);
                $data['image'] = 'assets/img/' . $filename;
            }
        }
        // dd($data);//VERIFIE LES DONNEES ENVOYEES
        $id =  Article::create($data)->id;
        $data_pub = [
            "idarticle" => $id,
            "etat" => 1,
            "publish_at" => $request->input("datepublication")
        ];
        Publication::create($data_pub);
        return redirect()->back()->with('success','Article enregistré.');
    }

    public function DeleteArticle($slug) {
        $file_without_extension = basename($slug, ".html"); // renvoie "un-titre-très-long_unid"
        $idarticle = substr(strrchr($file_without_extension, "_"), 1);
        $publication = Publication::firstWhere('idarticle',$idarticle);
        $publication->etat = 11;
        $publication->save();
        return redirect()->back()->with('success','Article Supprimé.');
    }

    public function ToAddArticle() {
        $categorie = Article::distinct()->pluck('categorie')->toArray();
        return view('CreateArticle',['categorie' => $categorie]);
    }

    public function ReAddArticle($slug) {
        $file_without_extension = basename($slug, ".html"); // renvoie "un-titre-très-long_unid"
        $idarticle = substr(strrchr($file_without_extension, "_"), 1);
        $publication = Publication::firstWhere('idarticle',$idarticle);
        $publication->etat = 1;
        $publication->save();
        return redirect()->back()->with('success','l\'article a été republié.');
    }

    public function ToUpdateArticle($slug) {
        $file_without_extension = basename($slug, ".html"); // renvoie "un-titre-très-long_unid"
        $idarticle = substr(strrchr($file_without_extension, "_"), 1);
        $article =  Article::find($idarticle);
        $categorie = Article::where('categorie','!=',$article->categorie)->distinct()->pluck('categorie')->toArray();
        return view('UpdateArticle',[
            'categorie' => $categorie,
            'article' => $article
        ]);
    }

    public function ExportPDF($slug) {
        $file_without_extension = basename($slug, ".html"); // renvoie "un-titre-très-long_unid"
        $idarticle = substr(strrchr($file_without_extension, "_"), 1);
        $article =  Article::find($idarticle);
        $dompdf = new Dompdf();
        $html = view('Article', compact('article'))->render();
        $dompdf->loadHtml($html);
        $dompdf->render();
        $pdfname = $article->getPublication()->publish_at." - ".$article->titre.".pdf";
        return $dompdf->stream($pdfname);
    }
}

