<?php

namespace Database\Seeders;

use App\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiteraryQuoteSeeder extends Seeder
{
    /**
     * Primeiro lote da "citação literária do dia" (ver dashboard.blade.php +
     * DashboardController::index()). Cada trecho foi conferido palavra por
     * palavra contra o texto integral em domínio público (Project Gutenberg —
     * ver source_url), com ortografia modernizada (a edição original de
     * 1880-1899 usa grafia pré-reforma: "cousa", "sciencia" etc.) — só a
     * grafia muda, nunca a palavra ou a ordem das palavras. Não gerado de
     * memória, propositalmente, pra não arriscar citação inventada.
     *
     * Lote inicial pequeno (12) por decisão do usuário: crescer aos poucos
     * com conferência, em vez de gerar uma lista grande de uma vez sem
     * checar cada trecho contra a fonte.
     *
     * 2026-08-12: usuário pediu que os trechos sejam em português e que o
     * acervo inclua também filosofia clássica, não só literatura nacional.
     * Fonte de tradução PT verificável pra filosofia clássica é escassa
     * (Project Gutenberg praticamente não tem; Domínio Público bloqueia
     * scraping — 403); usado pt.wikisource.org via API/action=raw como
     * segunda fonte (mesma regra: nunca WebFetch pra texto verbatim, nunca
     * de memória). Achado só 1 tradução PT de filósofo clássico (Sêneca,
     * Cartas a Lucílio) + o soneto "Nirvana" de Antero de Quental, que foi
     * poeta E filósofo (Geração de 70) escrevendo originalmente em
     * português — sem risco de tradução nenhum.
     */
    public function run(): void
    {
        $quotes = [
            [
                'book' => 'Dom Casmurro',
                'author' => 'Machado de Assis',
                'excerpt' => "Uma noite destas, vindo da cidade para o Engenho Novo, encontrei no trem da Central um rapaz aqui do bairro, que eu conheço de vista e de chapéu. Cumprimentou-me, sentou-se ao pé de mim, falou da lua e dos ministros, e acabou recitando-me versos. A viagem era curta, e os versos pode ser que não fossem inteiramente maus. Sucedeu, porém, que, como eu estava cansado, fechei os olhos três ou quatro vezes; tanto bastou para que ele interrompesse a leitura e metesse os versos no bolso.",
                'justification' => "É a cena que dá nome ao livro: o narrador cochila durante a leitura de versos de um desconhecido e ganha o apelido \"Dom Casmurro\" por isso — sem querer, por um gesto pequeno e sem malícia. Um lembrete de como somos rotulados por detalhes que, na hora, pareciam não significar nada.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55752',
            ],
            [
                'book' => 'Dom Casmurro',
                'author' => 'Machado de Assis',
                'excerpt' => "Retórica dos namorados, dá-me uma comparação exata e poética para dizer o que foram aqueles olhos de Capitu. Não me acode imagem capaz de dizer, sem quebra da dignidade do estilo, o que eles foram e me fizeram. Olhos de ressaca? Vá, de ressaca. É o que me dá ideia daquela feição nova. Traziam não sei que fluido misterioso e enérgico, uma força que arrastava para dentro, como a vaga que se retira da praia, nos dias de ressaca. Para não ser arrastado, agarrei-me às outras partes vizinhas, às orelhas, aos braços, aos cabelos espalhados pelos ombros; mas tão depressa buscava as pupilas, a onda que saía delas vinha crescendo, cava e escura, ameaçando envolver-me, puxar-me e tragar-me.",
                'justification' => "A mais famosa descrição de olhos da literatura brasileira — \"olhos de ressaca\". Vale menos pela beleza da imagem e mais pelo que descreve: a sensação física de ser puxado por algo (ou alguém) antes mesmo de entender o que está sentindo.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55752',
            ],
            [
                'book' => 'Dom Casmurro',
                'author' => 'Machado de Assis',
                'excerpt' => "A vida é uma ópera e uma grande ópera. O tenor e o barítono lutam pelo soprano, em presença do baixo e dos comprimários, quando não são o soprano e o contralto que lutam pelo tenor, em presença do mesmo baixo e dos mesmos comprimários. Há coros numerosos, muitos bailados, e a orquestração é excelente...",
                'justification' => "Um velho tenor explica ao narrador sua teoria de que a vida é uma ópera — feita de papéis, disputas de protagonismo e coreografia coletiva. Uma metáfora e tanto pra pensar em qualquer trabalho que se faça em equipe, com egos, palcos e bastidores.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55752',
            ],
            [
                'book' => 'Memórias Póstumas de Brás Cubas',
                'author' => 'Machado de Assis',
                'excerpt' => "Este último capítulo é todo de negativas. Não alcancei a celebridade do emplasto, não fui ministro, não fui califa, não conheci o casamento. Verdade é que, ao lado dessas faltas, coube-me a boa fortuna de não comprar o pão com o suor do meu rosto. Mais: não padeci a morte de D. Plácida, nem a semidemência do Quincas Borba. Somadas umas coisas e outras, qualquer pessoa imaginará que não houve míngua nem sobra, e consequentemente que saí quite com a vida. E imaginará mal; porque, ao chegar a este outro lado do mistério, achei-me com um pequeno saldo, que é a derradeira negativa deste capítulo de negativas: — Não tive filhos, não transmiti a nenhuma criatura o legado da nossa miséria.",
                'justification' => "O narrador — que escreve já morto, do outro lado da vida — fecha suas memórias fazendo um balanço do que NÃO conquistou. E encontra, no fim, um alívio inesperado numa única ausência. Uma provocação sobre como medimos o valor de uma vida, e o que decidimos deixar (ou não) pra quem vem depois.",
                'source_url' => 'https://www.gutenberg.org/ebooks/54829',
            ],
            [
                'book' => 'Quincas Borba',
                'author' => 'Machado de Assis',
                'excerpt' => "Que abismo há entre o espírito e o coração! O espírito do ex-professor, vexado daquele pensamento, arrepiou caminho, buscou outro assunto, uma canoa que ia passando; o coração, porém, deixou-se estar a bater de alegria. Que lhe importa a canoa nem o canoeiro, que os olhos de Rubião acompanham, arregalados? Ele, coração, vai dizendo que, uma vez que a mana Piedade tinha de morrer, foi bom que não casasse; podia vir um filho ou uma filha... — Bonita canoa! — Antes assim! — Como obedece bem aos remos do homem! — O certo é que eles estão no céu!",
                'justification' => "Rubião tenta se distrair de um pensamento que o envergonha, mas o coração já decidiu o que sentir antes que a razão tenha tempo de opinar. Um retrato preciso — e nada solene — de como pensamento e sentimento raramente remam na mesma direção.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55682',
            ],
            [
                'book' => 'Quincas Borba',
                'author' => 'Machado de Assis',
                'excerpt' => "Esqueceu o projeto do sinete; mas a fórmula viveu no espírito de Rubião por alguns dias: — Ao vencedor, as batatas! Não a compreenderia antes do testamento; ao contrário, vimos que a achou obscura e sem explicação. Tão certo é que a paisagem depende do ponto de vista, e que o melhor modo de apreciar o chicote é ter-lhe o cabo na mão.",
                'justification' => "\"Ao vencedor, as batatas\" é a sátira mais citada de Machado ao darwinismo social — a ideia de que o forte vence por merecimento, e o resto é justificativa. A última frase, sobre o chicote, é a virada: quem está de um lado dele nunca vê o mundo do mesmo jeito que quem está do outro.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55682',
            ],
            [
                'book' => 'Memorial de Aires',
                'author' => 'Machado de Assis',
                'excerpt' => "Durante os meus trinta e tantos anos de diplomacia, algumas vezes vim ao Brasil, com licença. O mais do tempo vivi fora, em várias partes, e não foi pouco. Cuidei que não acabaria de me habituar novamente a esta outra vida de cá. Pois acabei. Certamente ainda me lembram coisas e pessoas de longe, diversões, paisagens, costumes, mas não morro de saudades por nada. Aqui estou, aqui vivo, aqui morrerei.",
                'justification' => "As primeiras linhas do último romance de Machado: um diplomata aposentado, de volta ao Brasil depois de décadas fora, constata sem drama que se reacostumou a casa. \"Aqui estou, aqui vivo, aqui morrerei\" — sobre pertencimento, sem nostalgia forçada.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55797',
            ],
            [
                'book' => 'Iracema',
                'author' => 'José de Alencar',
                'excerpt' => "Além, muito além daquela serra, que ainda azula no horizonte, nasceu Iracema. Iracema, a virgem dos lábios de mel, que tinha os cabelos mais negros que a asa da graúna, e mais longos que seu talhe de palmeira. O favo da jati não era doce como seu sorriso; nem a baunilha rescendia no bosque como seu hálito perfumado.",
                'justification' => "A abertura de um dos livros fundadores do romantismo brasileiro — e um anagrama de \"América\". Vale como lembrete de que toda identidade nacional começa sendo contada por alguém, de algum lugar, sobre algo que já ficou longe.",
                'source_url' => 'https://www.gutenberg.org/ebooks/67740',
            ],
            [
                'book' => 'Iracema',
                'author' => 'José de Alencar',
                'excerpt' => "Poti cismava. Em sua cabeça de mancebo morava o espírito de um abaeté. O chefe pitiguara pensava que o amor é como o cauim, o qual, bebido com moderação, fortalece o guerreiro, e, tomado em excesso, abate a coragem do herói. Ele sabia quanto veloz era o pé do tabajara; e esperava o momento de morrer defendendo o amigo.",
                'justification' => "Uma comparação simples — o amor (ou qualquer paixão forte) como uma bebida: na medida certa, dá força; em excesso, tira o discernimento. E, ao lado dela, um exemplo de lealdade que não pede nada em troca.",
                'source_url' => 'https://www.gutenberg.org/ebooks/67740',
            ],
            [
                'book' => 'O Cortiço',
                'author' => 'Aluísio Azevedo',
                'excerpt' => "Proprietário e estabelecido por sua conta, o rapaz atirou-se à labutação ainda com mais ardor, possuindo-se de tal delírio de enriquecer, que afrontava resignado as mais duras privações. Dormia sobre o balcão da própria venda, em cima de uma esteira, fazendo travesseiro de um saco de estopa cheio de palha.",
                'justification' => "O retrato de João Romão, que sacrifica o próprio corpo e conforto por décadas em nome de enriquecer. Azevedo não está elogiando essa dedicação obsessiva — o livro inteiro é uma crítica ao preço humano dela. Vale como contraponto a qualquer ideia de que \"trabalhar até doer\" é sempre virtude.",
                'source_url' => 'https://www.gutenberg.org/ebooks/69187',
            ],
            [
                'book' => 'Os Maias',
                'author' => 'Eça de Queirós',
                'excerpt' => "A casa que os Maias vieram habitar em Lisboa, no outono de 1875, era conhecida na vizinhança da rua de S. Francisco de Paula, e em todo o bairro das Janelas Verdes, pela casa do Ramalhete ou simplesmente o Ramalhete. Apesar deste fresco nome de vivenda campestre, o Ramalhete, sombrio casarão de paredes severas, com um renque de estreitas varandas de ferro no primeiro andar, e por cima uma tímida fila de janelinhas abrigadas à beira do telhado, tinha o aspecto tristonho de residência eclesiástica que competia a uma edificação do reinado da sr.ª D. Maria I: com uma sineta e com uma cruz no topo, assemelhar-se-ia a um colégio de jesuítas.",
                'justification' => "\"Ramalhete\" (buquê de flores) é o nome apelidado de uma casa severa, sombria, quase um convento. Eça abre o romance com esse contraste entre nome e substância — um convite a desconfiar sempre da etiqueta antes de conhecer o que ela cobre.",
                'source_url' => 'https://www.gutenberg.org/ebooks/40409',
            ],
            [
                'book' => 'O Mandarim',
                'author' => 'Eça de Queirós',
                'excerpt' => "No fundo da China existe um Mandarim mais rico que todos os reis de que a Fábula ou a História contam. Dele nada conheces, nem o nome, nem o semblante, nem a seda de que se veste. Para que tu herdes os seus cabedais infindáveis, basta que toques essa campainha, posta a teu lado, sobre um livro. Ele soltará apenas um suspiro, nesses confins da Mongólia. Será então um cadáver: e tu verás a teus pés mais ouro do que pode sonhar a ambição de um avaro. Tu, que me lês e és um homem mortal, tocarás tu a campainha?",
                'justification' => "O dilema que move a novela inteira: matar, à distância e sem risco algum, um estranho que você nunca vai ver, em troca de riqueza incalculável. Escrito em 1880, mas é basicamente a pergunta por trás de qualquer decisão cujo custo humano acontece longe demais pra ser sentido.",
                'source_url' => 'https://www.gutenberg.org/ebooks/16384',
            ],
            [
                'book' => 'Cartas a Lucílio (Carta 1 — Sobre Economizar Tempo)',
                'author' => 'Sêneca',
                'excerpt' => "Continue a agir assim, meu caro Lucílio — liberte-se para o seu próprio bem; reúna e economize o seu tempo, que até ultimamente foi retirado à força de você, ou despojado, ou simplesmente escapou de suas mãos. O tipo mais vergonhoso de perda, entretanto, é aquele que ocorre por descuido. Além disso, se você prestar muita atenção no problema, você descobrirá que a maior porção da sua vida passa enquanto nós estamos passando mal, uma boa parte enquanto nós não fazemos nada, e todo o tempo que nós fazemos aquilo que não é o propósito.",
                'justification' => "Uma das cartas mais citadas do estoicismo: pra Sêneca, o tempo perdido por descuido — sem nem perceber — é a forma mais vergonhosa de desperdício, porque, ao contrário do dinheiro, não tem como recuperar depois. Tradução de domínio público (Wikisource, comunitária) — a fluência não é de edição literária polida, mas o pensamento de Sêneca chega inteiro.",
                'source_url' => 'https://pt.wikisource.org/wiki/Cartas_a_Lucilio_-_Carta_1',
            ],
            [
                'book' => 'Sonetos — "Nirvana"',
                'author' => 'Antero de Quental',
                'excerpt' => "Para além do Universo luminoso,\nCheio de formas, de rumor, de lida,\nDe forças, de desejos e de vida,\nAbre-se como um vácuo tenebroso.\n\nA onda desse mar tumultuoso\nVem ali expirar, esmaecida...\nNuma imobilidade indefinida\nTermina ali o ser, inerte, ocioso...\n\nE quando o pensamento, assim absorto,\nEmerge a custo desse mundo morto\nE torna a olhar as coisas naturais,\n\nÀ bela luz da vida, ampla, infinita,\nSó vê com tédio, em tudo quanto fita,\nA ilusão e o vazio universais.",
                'justification' => "Antero de Quental não foi só poeta — foi filósofo, liderou a chamada \"Geração de 70\" e escreveu diretamente sobre filosofia. Neste soneto, escrito originalmente em português (sem risco nenhum de tradução), ele imagina o que existe além do universo visível: um vazio que absorve tudo. Denso, mas é exatamente o tipo de pergunta grande que a filosofia sempre fez.",
                'source_url' => 'https://pt.wikisource.org/wiki/Page:Sonetos_by_Antero_de_Quental.djvu/37',
            ],

            // ── Terceiro lote (2026-08-12, mesmo dia) — usuário pediu foco em
            // literatura brasileira/clássicos até chegar a 60, filosofia fica
            // pra depois. 8 livros novos baixados (Gutenberg), + mais garimpo
            // nos 8 já existentes. Mesma regra: nunca WebFetch, nunca memória.
            [
                'book' => 'Dom Casmurro',
                'author' => 'Machado de Assis',
                'excerpt' => "Talvez abuso um pouco das reminiscências osculares; mas a saudade é isto mesmo: é o passar e repassar das memórias antigas. Ora, de todas as daquele tempo creio que a mais doce é esta, a mais nova, a mais compreensiva, a que inteiramente me revelou a mim mesmo.",
                'justification' => "Uma das definições mais bonitas de saudade da literatura brasileira — não como um sentimento triste em si, mas como o simples ato de reviver, de tempos em tempos, memórias antigas.",
                'source_url' => 'https://www.gutenberg.org/ebooks/55752',
            ],
            [
                'book' => 'Memórias Póstumas de Brás Cubas',
                'author' => 'Machado de Assis',
                'excerpt' => "Saí, afastando-me dos grupos, e fingindo ler os epitáfios. E, aliás, gosto dos epitáfios; eles são, entre a gente civilizada, uma expressão daquele pio e secreto egoísmo que induz o homem a arrancar à morte um farrapo ao menos da sombra que passou. Daí vem, talvez, a tristeza inconsolável dos que levam os seus mortos à vala comum; parece-lhes que a podridão anônima os alcança a eles mesmos.",
                'justification' => "Uma reflexão sobre por que gostamos de epitáfios: é a tentativa humana de arrancar da morte um pedaço, por menor que seja, de quem passou por aqui. Também explica por que a vala comum, sem nome, dói tanto mais.",
                'source_url' => 'https://www.gutenberg.org/ebooks/54829',
            ],
            [
                'book' => 'Memórias Póstumas de Brás Cubas',
                'author' => 'Machado de Assis',
                'excerpt' => "Não podia sacudir dos olhos a cerimônia do enterro, nem dos ouvidos os soluços de Virgília. Os soluços, principalmente, tinham o som vago e misterioso de um problema. Virgília traíra o marido, com sinceridade; e agora chorava-o com sinceridade. Eis uma combinação difícil que não pude fazer em todo o trajeto; em casa, porém, apeando-me do carro, suspeitei que a combinação era possível, e até fácil. Meiga Natura! A taxa da dor é como a moeda de Vespasiano; não cheira à origem, e tanto se colhe do mal como do bem.",
                'justification' => "\"A moeda de Vespasiano\" vem da história do imperador romano que taxava até banheiros públicos — dinheiro não cheira à origem. Machado usa a imagem pra dizer que a dor é igual: não importa se vem de uma traição ou de um afeto genuíno, ela dói do mesmo jeito.",
                'source_url' => 'https://www.gutenberg.org/ebooks/54829',
            ],
            [
                'book' => 'Como e Porque Sou Romancista',
                'author' => 'José de Alencar',
                'excerpt' => "O mestre que eu tive foi esta esplêndida natureza que me envolve, e particularmente a magnificência dos desertos que eu perlustrei ao entrar na adolescência, e foram o pórtico majestoso por onde minha alma penetrou no passado de sua pátria. Daí, desse livro secular e imenso, é que eu tirei as páginas do Guarani, as de Iracema, e outras muitas que uma vida não bastaria a escrever. Daí, e não das obras de Chateaubriand, e menos das de Cooper, que não eram senão a cópia do original sublime, que eu havia lido com o coração.",
                'justification' => "Numa carta autobiográfica, Alencar conta de onde tirou a inspiração pra Iracema e O Guarani: não de outros escritores que admirava, mas da própria natureza brasileira, vivida e observada de perto. Um lembrete de que a fonte mais original nunca é a cópia de uma referência, mas a experiência direta.",
                'source_url' => 'https://www.gutenberg.org/ebooks/29040',
            ],
            [
                'book' => 'Triste Fim de Policarpo Quaresma',
                'author' => 'Lima Barreto',
                'excerpt' => "Policarpo era patriota. Desde moço, aí pelos vinte anos, o amor da pátria tomou-o todo inteiro. Não fora o amor comum, palrador e vazio; fora um sentimento sério, grave e absorvente. Nada de ambições políticas ou administrativas; o que Quaresma pensou, ou melhor: o que o patriotismo o fez pensar, foi num conhecimento inteiro do Brasil, levando-o a meditações sobre os seus recursos, para depois então apontar os remédios, as medidas progressivas, com pleno conhecimento de causa.",
                'justification' => "O retrato do patriotismo do Major Policarpo Quaresma — não o \"amor comum, palrador e vazio\", mas um sentimento sério o bastante pra levá-lo a estudar o Brasil de verdade, em vez de só discursar sobre ele. Uma distinção que vale pra qualquer convicção: fácil discursar, difícil se aprofundar.",
                'source_url' => 'https://www.gutenberg.org/ebooks/67535',
            ],
            [
                'book' => 'Poesias Completas — "Círculo Vicioso"',
                'author' => 'Machado de Assis',
                'excerpt' => "Bailando no ar, gemia inquieto vaga-lume:\n— \"Quem me dera que fosse aquela loura estrela,\nQue arde no eterno azul, como uma eterna vela!\"\nMas a estrela, fitando a lua, com ciúme:\n\n— \"Pudesse eu copiar o transparente lume,\nQue, da grega coluna à gótica janela,\nContemplou, suspirosa, a fronte amada e bela!\"\nMas a lua, fitando o sol, com azedume:\n\n— \"Mísera! tivesse eu aquela enorme, aquela\nClaridade imortal, que toda a luz resume!\"\nMas o sol, inclinando a rútila capela:\n\n— \"Pesa-me esta brilhante auréola de nume...\nEnfara-me esta azul e desmedida umbela...\nPor que não nasci eu um simples vaga-lume?\"",
                'justification' => "Uma fábula completa em catorze versos: o vaga-lume inveja a estrela, a estrela inveja a lua, a lua inveja o sol — e o sol, no fim, inveja o simples vaga-lume. O ciclo perfeito de nunca estar satisfeito com o que se é.",
                'source_url' => 'https://www.gutenberg.org/ebooks/61653',
            ],
            [
                'book' => 'Poesias Completas — "Soneto de Natal"',
                'author' => 'Machado de Assis',
                'excerpt' => "Um homem, — era aquela noite amiga,\nNoite cristã, berço do Nazareno, —\nAo relembrar os dias de pequeno,\nE a viva dança, e a lépida cantiga,\n\nQuis transportar ao verso doce e ameno\nAs sensações da sua idade antiga,\nNaquela mesma velha noite amiga,\nNoite cristã, berço do Nazareno.\n\nEscolheu o soneto... A folha branca\nPede-lhe a inspiração; mas, frouxa e manca,\nA pena não acode ao gesto seu.\n\nE, em vão lutando contra o metro adverso,\nSó lhe saiu este pequeno verso:\n\"Mudaria o Natal ou mudei eu?\"",
                'justification' => "Um homem tenta recuperar, em versos, a sensação de infância que sentia no Natal — e a inspiração não vem. Sobra só uma pergunta honesta: \"Mudaria o Natal ou mudei eu?\" Vale pra qualquer nostalgia: o que muda, quase sempre, somos nós.",
                'source_url' => 'https://www.gutenberg.org/ebooks/61653',
            ],
            [
                'book' => 'A Mão e a Luva',
                'author' => 'Machado de Assis',
                'excerpt' => "O resultado devia ser um. A vontade e a ambição, quando verdadeiramente dominam, podem lutar com outros sentimentos, mas hão de sempre vencer, porque elas são as armas do forte, e a vitória é dos fortes.",
                'justification' => "Uma personagem decide entre dois pretendentes, e Machado aproveita o momento pra uma observação seca sobre vontade e ambição: quando dominam de verdade, vencem qualquer outro sentimento — porque são armas de quem é forte, e a vitória é de quem é forte.",
                'source_url' => 'https://www.gutenberg.org/ebooks/53101',
            ],
            [
                'book' => 'O Guarani',
                'author' => 'José de Alencar',
                'excerpt' => "Não é neste lugar que ele deve ser visto; sim três ou quatro léguas acima de sua foz, onde é livre ainda, como o filho indômito desta pátria da liberdade. Ali, o Paquequer lança-se rápido sobre o seu leito, e atravessa as florestas como o tapir, espumando, deixando o pelo esparso pelas pontas de rochedo, e enchendo a solidão com o estampido de sua carreira.",
                'justification' => "A abertura de O Guarani descreve um rio livre nas montanhas, correndo solto pelas florestas — o mesmo rio que, mais abaixo, fica manso e \"escravo submisso\" ao entrar no rio maior. Alencar usa a paisagem pra falar de liberdade antes mesmo de qualquer personagem aparecer.",
                'source_url' => 'https://www.gutenberg.org/ebooks/67724',
            ],
        ];

        $organizationIds = Organization::pluck('id');
        if ($organizationIds->isEmpty()) {
            return;
        }

        // Idempotente por (organization_id, book, excerpt) — reseed não duplica.
        $existing = DB::connection('pgsql')->table('literary_quotes')
            ->whereIn('organization_id', $organizationIds)
            ->get(['organization_id', 'book', 'excerpt'])
            ->map(fn ($row) => $row->organization_id . '|' . $row->book . '|' . md5($row->excerpt))
            ->flip();

        $now = now();
        $rows = [];

        foreach ($organizationIds as $organizationId) {
            foreach ($quotes as $quote) {
                $key = $organizationId . '|' . $quote['book'] . '|' . md5($quote['excerpt']);
                if (isset($existing[$key])) {
                    continue;
                }

                $rows[] = [
                    'id'              => (string) Str::uuid(),
                    'organization_id' => $organizationId,
                    'book'            => $quote['book'],
                    'author'          => $quote['author'],
                    'excerpt'         => $quote['excerpt'],
                    'justification'   => $quote['justification'],
                    'source_url'      => $quote['source_url'],
                    'is_active'       => true,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }
        }

        if (!empty($rows)) {
            DB::connection('pgsql')->table('literary_quotes')->insert($rows);
        }
    }
}
