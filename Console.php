<?php namespace pulyavin\console;

class Console {
    # опции, разобранные функцией getopt()
    private $options = [];
    # массив аргументов, хранящийся в $_SERVER
    private $arguments = [];
    # выводимая строка
    private $text = null;

    public function __construct($shortopts = null, array $longopts = []) {
        $this->options = getopt($shortopts, $longopts);
        $this->arguments = $_SERVER['argv'];
    }

    /**
     * дописываем в конец (или начало) переданную строку, которая потом будет выведена
     * @param  string  $text   дописываемая строка
     * @param  boolean $at_end строка будет добавлена в начало? иначе в конец
     * @return object of chain
     */
    public function text($text = null, $atTheTop = false) {
        # добавляем строку в конец
        if (!$atTheTop) {
            $this->text .= $text;
        }
        # добавляем строку в начало
        else {
            $this->text = $text.$this->text;
        }

        return $this;
    }

    /**
     * из текущей выводимой строки создаёт ячейку таблицы равной длины
     * @param  integer $symbols   длина ячейки таблицы в символах
     * @param  string $align     выравнивание текста в ячейке: left, right или center
     * @param  string $delimiter разделитель (завершитель) ячейки
     * @return object of chain
     */
    public function column($symbols, $align = "left", $delimiter = "") {
        # очищаем выводимую троку от esc-символов
        $text = preg_replace("/\\033\[[0-9;]+m/i", "", $this->text);

        # считаем количество пробелов, которое нужно будет добавить
        $spaces = $symbols - mb_strlen($text, "UTF-8");
        $spaces = $spaces < 0 ? 0 : $spaces;
        $repeat = str_repeat(" ", $spaces);

        # right: пробелы слева
        if ($align == "right") {
            $this->text($repeat, true);
        }
        # center: по двум краям
        else if ($align == "right") {
            $spaces = ceil($spaces/2);
            $repeat = str_repeat(" ", $spaces);

            # половинку слева
            $this->text($repeat, true);
            # половинку справа
            $this->text($repeat);
        }
        # left: пробелы справа
        else {
            $this->text($repeat);
        }

        # выводим ячейчку
        $delimiter = !empty($delimiter) ? $delimiter.' ' : $delimiter;
        $this->stdout($delimiter);
        
        return $this;
    }

    /**
     * создаёт указанное количество переносов на новую строку
     * @param  integer $multiplier кол-во переносов
     * @return object of chain
     */
    public function lineFeed($multiplier = 1) {
        for ($i = 1; $i <= $multiplier; $i++) {
            $this->text(PHP_EOL);
        }
        
        return $this;
    }

    /**
     * выводит в STDOUT строку, формированную методами ->text() и ->style()
     * @param  string  $text добавить ли в конец какой-то текст
     * @param  boolean $eol  сделать ли перенос на новую строку?
     * @return object of chain
     */
    public function stdout($text = null, $eol = false) {
        $this->write(STDOUT, $text, $eol);

        return $this;
    }

    /**
     * выводит в STDERR строку, формированную методами ->text() и ->style()
     * @param  string  $text добавить ли в конец какой-то текст
     * @param  boolean $eol  сделать ли перенос на новую строку?
     * @return object of chain
     */
    public function stderr($text = null, $eol = false) {
        $this->write(STDERR, $text, $eol);

        return $this;
    }

    /**
     * читаем из STDIN
     * @param  [type] $prompt  [description]
     * @param  [type] $type    [description]
     * @param  [type] $default [description]
     * @return [type]          [description]
     */
    public function stdin($prompt = null, $type = null, $default = null) {
        # выводим предварительное сообщение
        if ($prompt) {
            $this->write(STDOUT, $prompt . ": ");
        }
        # читаем канал
        $stdin = fgets(STDIN);
        $stdin = strtolower(trim($stdin));

        return $stdin;
    }

    # очищаем терминал
    public function clear() {
        fwrite(STDOUT, "\033c");

        return $this;
    }

    # задаём стиль выводимой строки
    public function style($color = null, $style = null, $bgcolor = null) {
        if (
            $color == null
            &&
            $style == null
            &&
            $bgcolor == null
        ) {
            $color = 'default';
            $style = 'default';
            $bgcolor = 'default';
        }

        $patterns = [
            'color' => [
                'gray'          => 30,
                'black'         => 30,
                'red'           => 31,
                'green'         => 32,
                'yellow'        => 33,
                'blue'          => 34,
                'magenta'       => 35,
                'cyan'          => 36,
                'white'         => 37,
                'default'       => 39
            ],
            'style' => [
                'default'           => '0',
            
                'bold'              => 1,
                'faint'             => 2,
                'normal'            => 22,
                
                'italic'            => 3,
                'notitalic'         => 23,
                
                'underlined'        => 4,
                'doubleunderlined'  => 21,
                'notunderlined'     => 24,
                
                'blink'             => 5,
                'blinkfast'         => 6,
                'noblink'           => 25,
                
                'negative'          => 7,
                'positive'          => 27,
            ],
            'bgcolor' => [
                'gray'       => 40,
                'black'      => 40,
                'red'        => 41,
                'green'      => 42,
                'yellow'     => 43,
                'blue'       => 44,
                'magenta'    => 45,
                'cyan'       => 46,
                'white'      => 47,
                'default'    => 49,
            ],
        ];

        # собираем esc-последовательность
        $escape = "";
        $escape .= ($color) ? ";".$patterns['color'][$color] : "";
        $escape .= ($style) ? ";".$patterns['style'][$style] : "";
        $escape .= ($bgcolor) ? ";".$patterns['bgcolor'][$bgcolor] : "";

        # крепим к следующей выводимой строке
        $this->text .= "\033[".substr($escape, 1)."m";

        return $this;
    }

    /**
     * выводит формированную строку ->text в указанный канал
     * @param  channel  $channel канал, в который будем выводить
     * @param  string  $text    добавочный текст
     * @param  boolean $eol     делать ли перенос на новую строку?
     */
    private function write($channel, $text = null, $eol = false) {
        # соединяем строки
        $this->text($text);
        # завершаем переводом на новую строку
        if ($eol) {
            $this->text(PHP_EOL);
        }
        # завершаем ранее начатую стилизацию
        $this->style(0);
        # выводим в канал
        fwrite($channel, $this->text);
        # очищаем строку для дальнейшего сбора
        $this->text = null;
    }
}