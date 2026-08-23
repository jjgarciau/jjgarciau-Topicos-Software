<?php



namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use App\Models\Product;


class ProductController extends Controller
{


    public function index():View
    {
        $viewData=[];
        $viewData["title"]="Products-OnlineStore";
        $viewData["subtitle"]="List of products";
        $viewData["products"]= Product::all();
        return view('product.index')->with("viewData",$viewData);
    }

    public function show(string$id):View
    {
        $viewData=[];
        $product=Product::findOrFail($id);
        $viewData["title"]=$product["name"]."-OnlineStore";
        $viewData["subtitle"]=$product["name"]."-Productinformation";
        $viewData["product"]=$product;
        return view('product.show')->with("viewData",$viewData);
    }

    public function create(): View
    {
        $viewData = []; //to be sent to the view
        $viewData["title"] = "Create product";
 
        return view('product.create')->with("viewData",$viewData);
    }
 

    public function save(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate([
            "name" => "required",
            "price" => "required"
        ]);
        
        Product::create($request->only(["name","price"]));

        return back();
    }
}
