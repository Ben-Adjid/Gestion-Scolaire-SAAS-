// Fonction pour mapper le niveau à Collège ou Lycée
import { paysCourant } from './pays'
const getNiveauLabel = (niveau) => {
  if (!niveau) return ''
  const cleanNiveau = niveau.toString().trim().toLowerCase()
  
  if (
    /^(6|5|4|3)/.test(cleanNiveau) || 
    cleanNiveau.includes('coll') || 
    cleanNiveau.includes('clg')
  ) {
    return 'Collège'
  }
  
  if (
    /^(2|1|t)/.test(cleanNiveau) ||
    cleanNiveau.includes('seconde') || 
    cleanNiveau.includes('première') || 
    cleanNiveau.includes('premiere') || 
    cleanNiveau.includes('terminale') ||
    cleanNiveau.includes('lyc')
  ) {
    return 'Lycée'
  }
  
  return niveau.charAt(0).toUpperCase() + niveau.slice(1)
}

// Fonction pour grouper les matières par domaine
const groupMatieresByDomaine = (matieres) => {
  const domaines = {
    'Scientifiques': [],
    'Littéraires': [],
    'Langues': [],
    'EPS': [],
    'Autres': []
  }

  const scientifiques = ['Mathématiques', 'Physique-Chimie', 'SVT', 'Maths', 'Physique', 'Chimie', 'Sciences']
  const litteraires = ['Français', 'Histoire-Géographie', 'Philosophie', 'Histoire', 'Géographie', 'Philo', 'Littérature']
  const langues = ['Anglais', 'Espagnol', 'Allemand', 'Italien', 'Chinois', 'Arabe']
  const eps = ['EPS', 'Éducation Physique', 'Sport']

  matieres.forEach(matiere => {
    const nom = matiere.matiere.nom
    if (scientifiques.some(s => nom.includes(s))) {
      domaines['Scientifiques'].push(matiere)
    } else if (litteraires.some(l => nom.includes(l))) {
      domaines['Littéraires'].push(matiere)
    } else if (langues.some(l => nom.includes(l))) {
      domaines['Langues'].push(matiere)
    } else if (eps.some(e => nom.includes(e))) {
      domaines['EPS'].push(matiere)
    } else {
      domaines['Autres'].push(matiere)
    }
  })

  // Retourner seulement les domaines qui ont des matières
  return Object.entries(domaines)
    .filter(([, matieres]) => matieres.length > 0)
    .map(([nom, matieres]) => ({ nom, matieres }))
}

export async function downloadTablePdf({ title, subtitle, columns, rows, filename, landscape = false }) {
  const jsPDF = await chargerJsPDF()
  const autoTable = await chargerAutoTable()

  const doc = new jsPDF({ orientation: landscape ? 'landscape' : 'portrait' })
  const largeur = doc.internal.pageSize.getWidth()

  // Bandeau d'en-tête aux couleurs de l'école, titre en blanc.
  const couleur = couleurEcole()
  doc.setFillColor(...couleur)
  doc.rect(0, 0, largeur, 26, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFontSize(16)
  doc.text(title, 14, 14)
  if (subtitle) {
    doc.setFontSize(9)
    doc.setTextColor(226, 232, 240)
    doc.text(subtitle, 14, 21)
  }
  // Nom de l'établissement à droite du bandeau (le titre de l'onglet porte le nom de l'école connectée).
  const nomEcole = (typeof document !== 'undefined' && document.title) || ''
  if (nomEcole) {
    doc.setFontSize(10)
    doc.setTextColor(255, 255, 255)
    doc.text(nomEcole, largeur - 14, 14, { align: 'right' })
  }

  autoTable(doc, {
    head: [columns],
    body: rows,
    startY: 32,
    margin: { top: 32, bottom: 18, left: 14, right: 14 },
    styles: { fontSize: 9, cellPadding: 3, textColor: [21, 48, 42], lineColor: [228, 222, 211], lineWidth: 0.1 },
    headStyles: { fillColor: couleur, textColor: 255, fontStyle: "bold", halign: "center" },
    alternateRowStyles: { fillColor: [243, 240, 233] },
    didDrawPage: () => {
      // Pied de page : date de génération et numérotation.
      const hauteur = doc.internal.pageSize.getHeight()
      const page = doc.internal.getNumberOfPages()
      doc.setFontSize(8)
      doc.setTextColor(120, 113, 100)
      doc.text(`Généré le ${new Date().toLocaleDateString('fr-FR')}`, 14, hauteur - 8)
      doc.text(`Page ${page}`, largeur - 14, hauteur - 8, { align: 'right' })
    },
  })

  doc.save(filename)
}

export async function downloadElementPdf(element, filename) {
  const { default: html2canvas } = await import('html2canvas')
  const jsPDF = await chargerJsPDF()

  const canvas = await html2canvas(element, {
    scale: 2,
    backgroundColor: '#ffffff',
    useCORS: true,
    onclone: (clonedDoc) => {
      clonedDoc.querySelectorAll('.no-print').forEach((el) => el.remove())
    },
  })

  // JPEG haute qualité : un PNG pleine page pèse plusieurs Mo, le JPEG reste net à l'impression.
  const imgData = canvas.toDataURL('image/jpeg', 0.92)

  const pdf = new jsPDF('p', 'mm', 'a4')
  const pageWidth = pdf.internal.pageSize.getWidth()
  const pageHeight = pdf.internal.pageSize.getHeight()
  const margin = 10

  // Fit the image inside the A4 page, preserving the aspect ratio
  const maxWidth = pageWidth - margin * 2
  const maxHeight = pageHeight - margin * 2

  let width = maxWidth
  let height = (canvas.height * width) / canvas.width

  if (height > maxHeight) {
    height = maxHeight
    width = (canvas.width * height) / canvas.height
  }

  const x = (pageWidth - width) / 2
  const y = (pageHeight - height) / 2

  pdf.addImage(imgData, 'JPEG', x, y, width, height)
  pdf.save(filename)
}

export async function downloadBulletinsPdf({ bulletins, etablissement, trimestreLabel, filename }) {
  const jsPDF = await chargerJsPDF()
  const autoTable = await chargerAutoTable()

  const doc = new jsPDF('p', 'mm', 'a4')
  const pageWidth = doc.internal.pageSize.getWidth()
  const center = () => pageWidth / 2

  bulletins.forEach((bulletin, index) => {
    if (index > 0) doc.addPage()

    // En-tête
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(11)
    doc.setTextColor(15, 23, 42)
    doc.text(paysCourant().republique, center(), 12, { align: 'center' })

    doc.setFont('helvetica', 'italic')
    doc.setFontSize(9)
    doc.setTextColor(59, 130, 246)
    doc.text(etablissement.devise || paysCourant().devise, center(), 17, { align: 'center' })

    doc.setFont('helvetica', 'normal')
    doc.setTextColor(100, 116, 139)
    doc.text(etablissement.ministere || '', center(), 21, { align: 'center' })

    doc.setFont('helvetica', 'bold')
    doc.setFontSize(12)
    doc.setTextColor(15, 23, 42)
    doc.text(etablissement.nom || '', center(), 26, { align: 'center' })

    doc.setFontSize(11)
    doc.text(`BULLETIN DE NOTES — ${trimestreLabel}`, center(), 33, { align: 'center' })

    // Infos élève
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9)
    doc.setTextColor(15, 23, 42)
    const niveauLabel = getNiveauLabel(bulletin.classe?.niveau)
    doc.text(
      `Élève : ${bulletin.etudiant.prenom} ${bulletin.etudiant.nom}    Matricule : ${bulletin.etudiant.matricule || '—'}    Classe : ${bulletin.classe?.nom || '—'}    Niveau : ${niveauLabel || '—'}`,
      14,
      40
    )

    // Group subjects by domain
    const domaines = groupMatieresByDomaine(bulletin.matieres)
    let currentY = 40

    domaines.forEach((domaine) => {
      // Print domain name
      doc.setFont('helvetica', 'bold')
      doc.setFontSize(9)
      doc.setTextColor(75, 85, 99)
      doc.text(domaine.nom, 14, currentY + 6)

      const body = domaine.matieres.map((row) => [
        row.matiere.nom,
        String(row.matiere.coefficient),
        Number(row.moyenne).toFixed(2),
        row.moyenne_classe != null ? Number(row.moyenne_classe).toFixed(2) : '—',
        row.appreciation || '',
      ])

      autoTable(doc, {
        head: [['Matière', 'Coef.', 'Moyenne', 'Moy. classe', 'Appréciation']],
        body,
        startY: currentY + 8,
        margin: { left: 14, right: 14 },
        styles: { fontSize: 8, cellPadding: 2.5 },
        headStyles: { fillColor: couleurEcole(), textColor: 255, fontSize: 8 },
        alternateRowStyles: { fillColor: [248, 250, 252] },
      })

      currentY = doc.lastAutoTable.finalY
    })

    // Appréciation + summary + signature
    const finalY = currentY

    // Draw a line separator before the summary
    doc.setDrawColor(226, 232, 240)
    doc.line(14, finalY + 4, pageWidth - 14, finalY + 4)

    doc.setFont('helvetica', 'normal')
    doc.setFontSize(9)
    doc.setTextColor(15, 23, 42)
    doc.text(
      `Moyenne : ${bulletin.moyenne_generale ?? '—'}    Rang : ${bulletin.rang ? `${bulletin.rang}e/${bulletin.effectif}` : '—'}    Mention : ${bulletin.mention?.label || '—'}`,
      14,
      finalY + 10
    )

    doc.text(
      `Absences : ${bulletin.nb_absences ?? 0}    Retards : ${bulletin.nb_retards ?? 0}    Convocations : ${bulletin.nb_convocations ?? 0}`,
      14,
      finalY + 16
    )

    doc.text(`Appréciation générale : ${bulletin.appreciation_generale || '—'}`, 14, finalY + 24)

    doc.text(`Fait le ${new Date().toLocaleDateString('fr-FR')}`, 14, finalY + 34)
    doc.text(
      etablissement.responsable ? `Le Chef d'établissement, ${etablissement.responsable}` : "Le Chef d'établissement",
      pageWidth - 14,
      finalY + 34,
      { align: 'right' }
    )
  })

  doc.save(filename)
}


// jsPDF 4 et jspdf-autotable 5 exposent des exports nommés ; les versions précédentes, un export par défaut.
async function chargerJsPDF() {
  const module = await import('jspdf')
  return module.jsPDF || module.default
}

async function chargerAutoTable() {
  const module = await import('jspdf-autotable')
  return module.autoTable || module.default
}

// Couleur de l'école (variable --primary du thème actif), en RVB pour jsPDF. Vert par défaut.
function couleurEcole() {
  try {
    const valeur = getComputedStyle(document.documentElement).getPropertyValue('--primary').trim()
    const hex = /^#([0-9a-f]{6})$/i.exec(valeur)
    if (hex) {
      const n = parseInt(hex[1], 16)
      return [(n >> 16) & 255, (n >> 8) & 255, n & 255]
    }
  } catch {
    // Hors navigateur : couleur par défaut.
  }
  return [31, 92, 74]
}

// Date française lisible : 19/11/2026.
const dateFr = (d) => {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(d || ''))
  return m ? `${m[3]}/${m[2]}/${m[1]}` : (d || '—')
}

const MODES_REGLEMENT = {
  especes: 'Espèces',
  cheque: 'Chèque',
  virement: 'Virement',
  mobile_money: 'Mobile money',
  carte: 'Carte bancaire',
  en_ligne: 'Paiement en ligne',
}

// Montant lisible : espaces de milliers ordinaires (la police PDF n'affiche pas les espaces fines).
const sommeFr = (valeur, monnaie) =>
  `${Math.round(Number(valeur) || 0).toLocaleString('fr-FR').replace(/[  ]/g, ' ')} ${monnaie}`

// Données du reçu, identiques pour le PDF et l'aperçu à l'écran.
export function construireRecu({ paiement, frais, eleve = {}, etablissement = {} }) {
  const monnaie = etablissement.monnaie || 'FCFA'
  const annee = frais.annee_scolaire || etablissement.annee_scolaire || ''
  const anneeRef = /^(\d{4})/.exec(annee)?.[1] || String(new Date().getFullYear())
  const quand = paiement.enregistre_le || paiement.created_at
  let dateHeure = dateFr(paiement.date_paiement)
  if (quand) {
    const d = new Date(quand)
    if (!Number.isNaN(d.getTime())) {
      const jour = d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })
      const heure = d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
      dateHeure = `${jour} à ${heure}`
    }
  }
  const reste = Math.max(0, Number(frais.reste) || 0)

  return {
    numero: `REC-${anneeRef}-${String(paiement.id).padStart(4, '0')}`,
    dateHeure,
    ecole: {
      nom: etablissement.nom || '',
      adresse: etablissement.adresse || '',
      ville: etablissement.ville || '',
      pays: etablissement.pays_info?.nom || '',
      telephone: etablissement.telephone || '',
      email: etablissement.email || '',
      anneeScolaire: etablissement.annee_scolaire || annee || '',
    },
    payeur: paiement.payeur_affiche || '—',
    eleve: {
      nom: `${eleve.prenom ?? ''} ${eleve.nom ?? ''}`.trim() || '—',
      matricule: eleve.matricule || '—',
      classe: [eleve.classe, eleve.niveau ? getNiveauLabel(eleve.niveau) : ''].filter(Boolean).join(' • ') || '—',
    },
    frais: {
      libelle: `${frais.libelle ?? ''}${annee ? ` (${annee})` : ''}`,
      exigible: sommeFr(frais.montant_total, monnaie),
      totalPaye: sommeFr(frais.montant_paye, monnaie),
      reste: sommeFr(reste, monnaie),
      soldé: reste <= 0,
    },
    montantEncaisse: sommeFr(paiement.montant, monnaie),
    mode: MODES_REGLEMENT[paiement.mode_paiement] || paiement.mode_paiement || '—',
    reference: paiement.reference || '',
    agent: paiement.agent?.name || paiement.agent || '',
  }
}

// Logo de l'école ; à défaut, le logo vectoriel de la plateforme (icône PWA).
async function chargerLogo(cheminLogo) {
  const sources = [cheminLogo ? `/${cheminLogo}` : null, '/icons/icon-512x512.png'].filter(Boolean)
  for (const src of sources) {
    try {
      return await new Promise((resolve, reject) => {
        const img = new Image()
        img.onload = () => {
          // Logo réduit (256 px max) sur fond blanc, en JPEG : le reçu reste léger (< 200 Ko).
          const echelle = Math.min(1, 256 / Math.max(img.naturalWidth, img.naturalHeight))
          const canevas = document.createElement('canvas')
          canevas.width = Math.max(1, Math.round(img.naturalWidth * echelle))
          canevas.height = Math.max(1, Math.round(img.naturalHeight * echelle))
          const contexte = canevas.getContext('2d')
          contexte.fillStyle = '#ffffff'
          contexte.fillRect(0, 0, canevas.width, canevas.height)
          contexte.drawImage(img, 0, 0, canevas.width, canevas.height)
          resolve(canevas.toDataURL('image/jpeg', 0.9))
        }
        img.onerror = reject
        img.src = src
      })
    } catch {
      // Essai de la source suivante.
    }
  }
  return null
}

// Reçu de paiement officiel (quittance de caisse), A4, aux couleurs de l'école.
export async function downloadRecuPdf({ paiement, frais, eleve, etablissement, filename }) {
  const jsPDF = await chargerJsPDF()
  const recu = construireRecu({ paiement, frais, eleve, etablissement })
  const doc = new jsPDF('p', 'mm', 'a4')
  const largeur = doc.internal.pageSize.getWidth()
  const hauteur = doc.internal.pageSize.getHeight()
  const [r, g, b] = couleurEcole()
  const teinte = [Math.round(r * 0.08 + 255 * 0.92), Math.round(g * 0.08 + 255 * 0.92), Math.round(b * 0.08 + 255 * 0.92)]
  const marge = 16
  const gris = [107, 114, 128]
  const encre = [31, 42, 40]

  // Bandeau d'en-tête : logo, identité officielle, année scolaire.
  doc.setFillColor(r, g, b)
  doc.rect(0, 0, largeur, 50, 'F')
  const logo = await chargerLogo(etablissement.logo_path)
  let xNom = marge
  if (logo) {
    doc.setFillColor(255, 255, 255)
    doc.roundedRect(marge, 11, 28, 28, 3, 3, 'F')
    doc.addImage(logo, 'JPEG', marge + 2, 13, 24, 24)
    xNom = marge + 34
  }
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(16)
  doc.text(recu.ecole.nom, xNom, 20)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  const lieu = [recu.ecole.adresse, recu.ecole.ville, recu.ecole.pays].filter(Boolean).join(' — ')
  const contact = [recu.ecole.telephone ? `Tél : ${recu.ecole.telephone}` : '', recu.ecole.email].filter(Boolean).join(' · ')
  if (lieu) doc.text(lieu, xNom, 28)
  if (contact) doc.text(contact, xNom, 34)

  doc.setFontSize(8)
  doc.text('ANNÉE SCOLAIRE', largeur - marge, 19, { align: 'right' })
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(13)
  doc.text(recu.ecole.anneeScolaire || '—', largeur - marge, 27, { align: 'right' })

  // Titre solennel et références.
  doc.setTextColor(r, g, b)
  doc.setFontSize(14)
  doc.text('REÇU DE PAIEMENT / QUITTANCE DE CAISSE', largeur / 2, 64, { align: 'center' })
  doc.setDrawColor(r, g, b)
  doc.setLineWidth(0.6)
  doc.line(largeur / 2 - 55, 67, largeur / 2 + 55, 67)

  doc.setTextColor(...encre)
  doc.setFontSize(11)
  doc.text(`N° de reçu : ${recu.numero}`, marge, 78)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  doc.text(`Date de l’opération : ${recu.dateHeure}`, largeur - marge, 78, { align: 'right' })

  // Identification de l'élève.
  doc.setFillColor(...teinte)
  doc.roundedRect(marge, 84, largeur - marge * 2, 30, 3, 3, 'F')
  const champ = (libelle, valeur, x, y) => {
    doc.setTextColor(...gris)
    doc.setFont('helvetica', 'normal')
    doc.setFontSize(8)
    doc.text(libelle.toUpperCase(), x, y)
    doc.setTextColor(...encre)
    doc.setFont('helvetica', 'bold')
    doc.setFontSize(11)
    doc.text(String(valeur), x, y + 6)
  }
  champ('Élève', recu.eleve.nom, marge + 6, 93)
  champ('Matricule', recu.eleve.matricule, marge + 6, 105)
  champ('Classe et niveau', recu.eleve.classe, largeur / 2 + 6, 93)
  champ('Payeur', recu.payeur, largeur / 2 + 6, 105)

  // Tableau financier.
  const yTable = 124
  const colLibelle = marge + 4
  const colExigible = 112
  const colEncaisse = 146
  const colMode = largeur - marge - 4
  doc.setFillColor(r, g, b)
  doc.rect(marge, yTable, largeur - marge * 2, 9, 'F')
  doc.setTextColor(255, 255, 255)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(8.5)
  doc.text('Libellé des frais', colLibelle, yTable + 6)
  doc.text('Montant exigible', colExigible, yTable + 6, { align: 'right' })
  doc.text('Encaissé ce jour', colEncaisse, yTable + 6, { align: 'right' })
  doc.text('Mode de règlement', colMode, yTable + 6, { align: 'right' })

  doc.setTextColor(...encre)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(10)
  const yLigne = yTable + 17
  doc.text(recu.frais.libelle, colLibelle, yLigne)
  doc.text(recu.frais.exigible, colExigible, yLigne, { align: 'right' })
  doc.setFont('helvetica', 'bold')
  doc.text(recu.montantEncaisse, colEncaisse, yLigne, { align: 'right' })
  doc.setFont('helvetica', 'normal')
  doc.text(recu.mode, colMode, yLigne, { align: 'right' })
  doc.setDrawColor(220, 220, 220)
  doc.setLineWidth(0.2)
  doc.line(marge, yLigne + 5, largeur - marge, yLigne + 5)

  // Situation après ce paiement.
  doc.setFontSize(10)
  doc.text('Total déjà encaissé', colLibelle, yLigne + 14)
  doc.text(recu.frais.totalPaye, colLibelle + 110, yLigne + 14, { align: 'right' })
  doc.text('Reste à payer', colLibelle, yLigne + 22)
  doc.text(recu.frais.reste, colLibelle + 110, yLigne + 22, { align: 'right' })

  // Montant encaissé, en évidence.
  const yMontant = yLigne + 34
  doc.setFillColor(...teinte)
  doc.setDrawColor(r, g, b)
  doc.setLineWidth(0.6)
  doc.roundedRect(marge, yMontant, largeur - marge * 2, 20, 3, 3, 'FD')
  doc.setTextColor(r, g, b)
  doc.setFont('helvetica', 'bold')
  doc.setFontSize(11)
  doc.text('MONTANT ENCAISSÉ', marge + 6, yMontant + 12.5)
  doc.setFontSize(17)
  doc.text(recu.montantEncaisse, largeur - marge - 6, yMontant + 13, { align: 'right' })

  // Badge de solde : vert si soldé, ambre sinon.
  const yBadge = yMontant + 28
  const libelleBadge = recu.frais.soldé ? 'SOLDÉ' : `RESTE À PAYER : ${recu.frais.reste}`
  doc.setFontSize(11)
  const largeurBadge = doc.getTextWidth(libelleBadge) + 14
  if (recu.frais.soldé) doc.setFillColor(22, 163, 74)
  else doc.setFillColor(217, 119, 6)
  doc.roundedRect(marge, yBadge, largeurBadge, 9, 2, 2, 'F')
  doc.setTextColor(255, 255, 255)
  doc.text(libelleBadge, marge + 7, yBadge + 6.2)

  // Agent de caisse et cadre cachet / signature.
  doc.setTextColor(...encre)
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(9)
  doc.text(`Encaissé par : ${recu.agent || '—'}`, marge, yBadge + 22)
  if (recu.reference) doc.text(`Référence : ${recu.reference}`, marge, yBadge + 28)

  doc.setDrawColor(...gris)
  doc.setLineWidth(0.3)
  doc.roundedRect(largeur - marge - 70, yBadge + 6, 70, 36, 2, 2, 'S')
  doc.setFontSize(8)
  doc.setTextColor(...gris)
  doc.text('Pour acquit', largeur - marge - 66, yBadge + 11)
  doc.text('Cachet & signature de l’établissement', largeur - marge - 35, yBadge + 39, { align: 'center' })

  // Mention légale et pied de page.
  doc.setDrawColor(220, 220, 220)
  doc.line(marge, hauteur - 26, largeur - marge, hauteur - 26)
  doc.setFont('helvetica', 'italic')
  doc.setFontSize(8)
  doc.setTextColor(...gris)
  doc.text(
    'Ce reçu est délivré à titre de justificatif de paiement officiel. Aucun duplicata ne sera fourni sans motif valable.',
    largeur / 2,
    hauteur - 19,
    { align: 'center' }
  )
  doc.setFont('helvetica', 'normal')
  doc.setFontSize(7.5)
  doc.text(`Édité le ${new Date().toLocaleDateString('fr-FR')} — ${recu.ecole.nom}`, largeur / 2, hauteur - 13, { align: 'center' })

  doc.save(filename)
}
