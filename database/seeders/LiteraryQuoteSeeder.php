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
