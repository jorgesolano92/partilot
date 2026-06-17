<?php

namespace App\Http\Controllers;

class LegalController extends Controller
{
    public function avisoLegal()
    {
        return view('legal.aviso-legal');
    }

    public function politicaDePrivacidad()
    {
        return view('legal.politica-de-privacidad');
    }

    public function politicaDeCookies()
    {
        return view('legal.politica-de-cookies');
    }

    public function terminosYCondiciones()
    {
        return view('legal.terminos-y-condiciones');
    }
}
