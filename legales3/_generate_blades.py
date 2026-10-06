# -*- coding: utf-8 -*-
"""Generate contract/legal Blade templates from legales3 extracted DOCX text."""
from __future__ import annotations

import html
import pathlib
import re

BASE = pathlib.Path(__file__).resolve().parent.parent
EXT = pathlib.Path(__file__).resolve().parent / "_extracted"
OUT = pathlib.Path(__file__).resolve().parent / "_generated"
OUT.mkdir(exist_ok=True)

PUB = "4 de septiembre de 2026"
LEGAL = "legal@partilot.es"
LOPD = "lopd@partilot.es"


def esc(s: str) -> str:
    return html.escape(s, quote=False)


def apply_placeholders(text: str) -> str:
    text = text.replace("[FECHA DE PUBLICACIÓN]", PUB)
    text = text.replace("[EMAIL LEGAL]", LEGAL)
    text = text.replace("[EMAIL PROTECCIÓN DE DATOS]", LOPD)
    text = re.sub(
        r"están disponibles en la página de precios de la Plataforma, accesible en \[URL PÁGINA DE PRECIOS\], y en la sección de configuración del panel de cada Punto de Venta Autorizado\.",
        "están disponibles en la sección de configuración del panel de cada Punto de Venta Autorizado.",
        text,
    )
    text = re.sub(r",?\s*accesible en \[URL PÁGINA DE PRECIOS\]", "", text)
    text = text.replace("[URL PÁGINA DE PRECIOS]", "")
    return text


def paras(path: pathlib.Path) -> list[str]:
    return [p.strip() for p in path.read_text(encoding="utf-8").splitlines() if p.strip()]


def para_html(p: str) -> str:
    p = apply_placeholders(p)
    if p in {"REUNIDOS", "EXPONEN", "CLÁUSULAS"} or p.startswith("ANEXO ") or p.startswith("DOCUMENTO "):
        return f"<p><strong>{esc(p)}</strong></p>"
    if p.startswith("Capítulo "):
        return f"<p><strong>{esc(p)}</strong></p>"
    # Clause titles are standalone lines in the source DOCX.
    if re.match(r"^CLÁUSULA\s+\d+", p):
        return f"<p><strong>{esc(p)}</strong></p>"
    if p.isupper() and len(p) < 90 and "PARTILOT" in p:
        return f"<p style=\"text-align:center;\"><strong>{esc(p)}</strong></p>"
    if p.startswith("CONTRATO ") or p.startswith("ENTRE PARTILOT"):
        return f"<p style=\"text-align:center;\"><strong>{esc(p)}</strong></p>"
    return f"<p>{esc(p)}</p>"


def write_contract_body(src_name: str, out_name: str) -> None:
    rows = paras(EXT / src_name)
    cut = next((i for i, p in enumerate(rows) if p.startswith("ANEXO I")), len(rows))
    body = rows[:cut]
    lines = ['<div class="contract-document">']
    for i, p in enumerate(body):
        lines.append("    " + para_html(p))
        if i == 1:
            lines.append('    <p>Referencia: <strong>{{ $contractReference }}</strong></p>')
    lines.append("</div>")
    (OUT / out_name).write_text("\n".join(lines) + "\n", encoding="utf-8")
    print(f"{out_name}: {len(body)} paragraphs")


def wrap_legal(title: str, version_line: str, body_paras: list[str], footer: str | None = None) -> str:
    out: list[str] = [
        "@extends('layouts.legal')",
        "",
        f"@section('title', '{title}')",
        "",
        "@section('content')",
        f"<h1>{esc(title)}</h1>",
        "",
        f"<p>{esc(version_line)}</p>",
        "",
    ]
    for p in body_paras:
        p = apply_placeholders(p)
        if p.startswith("DOCUMENTO "):
            continue
        if p.startswith("Capítulo ") or p in {"Preámbulo e identificación del titular", "Tarifas del servicio"}:
            out.append(f"<h2>{esc(p)}</h2>")
            out.append("")
            continue
        if re.match(r"^Artículo\s+", p):
            m = re.match(
                r"^(Artículo\s+[\d\.]+(?:\s+bis|\s+ter)?\.?\s*[^\n]{0,100}?)(\s+)(.{40,})$",
                p,
            )
            if m:
                title_part = m.group(1).rstrip(".")
                out.append(f"<h3>{esc(title_part)}</h3>")
                out.append("")
                out.append(f"<p>{esc(m.group(3))}</p>")
            else:
                m2 = re.match(r"^(Artículo\s+[\d\.]+(?:\s+bis|\s+ter)?[^.]*\.)\s*(.+)$", p)
                if m2:
                    out.append(f"<h3>{esc(m2.group(1).rstrip('.'))}</h3>")
                    out.append("")
                    out.append(f"<p>{esc(m2.group(2))}</p>")
                elif len(p) < 110:
                    out.append(f"<h3>{esc(p)}</h3>")
                else:
                    out.append(f"<p>{esc(p)}</p>")
            out.append("")
            continue
        mdef = re.match(
            r"^([A-ZÁÉÍÓÚÑ][A-Za-zÁÉÍÓÚáéíóúñÑ /()0-9.\-]{1,90}):\s+(.+)$",
            p,
        )
        if mdef and not p.startswith("PARTILOT,"):
            out.append(f"<p><strong>{esc(mdef.group(1))}:</strong> {esc(mdef.group(2))}</p>")
            out.append("")
            continue
        out.append(f"<p>{esc(p)}</p>")
        out.append("")
    if footer:
        out.append(footer)
        out.append("")
    out.append("@endsection")
    out.append("")
    return "\n".join(out)


def write_marco() -> None:
    marco = [apply_placeholders(p) for p in paras(EXT / "PARTILOT Marco Legal Integral v11 2 FINAL.txt")]
    idx_i = next(i for i, p in enumerate(marco) if p.startswith("DOCUMENTO I"))
    idx_ii = next(i for i, p in enumerate(marco) if p.startswith("DOCUMENTO II"))
    idx_iii = next(i for i, p in enumerate(marco) if p.startswith("DOCUMENTO III"))
    idx_iv = next(i for i, p in enumerate(marco) if p.startswith("DOCUMENTO IV"))

    version_line = next((p for p in marco if p.startswith("Versión") or p.startswith("Version")), f"Versión 11.2 | Entrada en vigor: {PUB}")
    version_line = apply_placeholders(version_line)
    version_line = re.sub(r"Versi[oó]n\s+11\.\d+", "Versión 11.2", version_line)

    tcu_body: list[str] = []
    for p in marco[idx_i + 1 : idx_ii]:
        if "tarifas vigentes aplicables" in p.lower() or "página de precios" in p.lower():
            tcu_body.append(
                "Las tarifas vigentes aplicables al servicio de gestión de PARTILOT están disponibles "
                "en la sección de configuración del panel de cada Punto de Venta Autorizado. "
                "Las tarifas aplicables a cada operación son las vigentes en el momento de la "
                "contratación del servicio correspondiente."
            )
            continue
        tcu_body.append(p)

    link_note = (
        "Este documento forma parte del marco legal integral de PARTILOT, junto con la "
        "Política de Privacidad, la Política de Cookies y el Acuerdo de Tratamiento de Datos."
    )
    (OUT / "terminos-y-condiciones.blade.php").write_text(
        wrap_legal(
            "Términos y Condiciones de Uso",
            version_line,
            [link_note] + tcu_body,
            "<p><em>© PARTILOT, S.L.U. — Términos y Condiciones de Uso — Contacto: legal@partilot.es</em></p>",
        ),
        encoding="utf-8",
    )
    (OUT / "politica-de-privacidad.blade.php").write_text(
        wrap_legal(
            "Política de Privacidad",
            version_line,
            marco[idx_ii + 1 : idx_iii],
            "<p><em>© PARTILOT, S.L.U. — Política de Privacidad — Contacto: lopd@partilot.es</em></p>",
        ),
        encoding="utf-8",
    )
    (OUT / "politica-de-cookies.blade.php").write_text(
        wrap_legal(
            "Política de Cookies",
            version_line,
            marco[idx_iii + 1 : idx_iv],
            "<p><em>© PARTILOT, S.L.U. — Política de Cookies</em></p>",
        ),
        encoding="utf-8",
    )

    atd = [p for p in marco[idx_iv + 1 :] if not p.startswith("©")]
    (OUT / "acuerdo-tratamiento-datos.partial.html").write_text(
        "\n".join(f"<p>{esc(apply_placeholders(p))}</p>" for p in atd) + "\n",
        encoding="utf-8",
    )
    print(f"Marco: TCU={len(tcu_body)} priv={idx_iii - idx_ii - 1} cookies={idx_iv - idx_iii - 1} atd={len(atd)}")


if __name__ == "__main__":
    write_contract_body(
        "PARTILOT Contrato SaaS PuntoVentaAutorizado v3 FINAL.txt",
        "administration_saas_clauses_body.blade.php",
    )
    write_contract_body(
        "PARTILOT Contrato Marco Entidad v4 FINAL.txt",
        "entity_framework_clauses_body.blade.php",
    )
    write_marco()
    print("OK", OUT)
